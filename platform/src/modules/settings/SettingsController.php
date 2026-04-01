<?php
class SettingsController {
    public function index(): void { View::redirect('/settings/vat'); }

    private function render(string $view, string $title, string $active, array $extra = []): void {
        View::renderWithLayout("settings/{$view}", array_merge([
            'pageTitle'=>$title,
            'activePage'=>$active,
            'settings'=>Settings::get(),
        ], $extra));
    }

    public function vat(): void { $this->render('vat', 'Настройки — ДДС', 'settings-vat'); }

    public function prices(): void {
        Auth::requireAdmin();
        $columns = ProductDB::getAllColumnsMeta();
        $formulaMap = ProductDB::getFormulaMap();
        foreach ($columns as &$col) {
            $f = $formulaMap[$col['name']] ?? null;
            $col['formula'] = $f ? ProductDB::tokensToHumanExpression($f['tokens']) : '';
            $col['rounding'] = $f['rounding'] ?? 2;
        }
        unset($col);
        $columns = array_values(array_filter($columns, static function(array $col): bool {
            return (($col['data_type'] ?? 'text') === 'number') || !empty($col['is_formula']);
        }));
        $this->render('prices', 'Настройки — Формули', 'settings-prices', [
            'columns' => $columns,
            'formulaMap' => $formulaMap,
            'formulaHistory' => ProductDB::getFormulaHistory(),
            'columnSlugMap' => ProductDB::getColumnSlugMap(),
            'formulaVersions' => ProductDB::getFormulaVersions(),
            'formulasLocked' => !empty(Settings::get()['formulas_locked']),
        ]);
    }

    public function formulas(): void { View::redirect('/settings/prices'); }
    public function integrations(): void { $this->render('integrations', 'Настройки — Интеграции', 'settings-integrations'); }

    public function system(): void {
        Auth::requireAdmin();
        $users = UserStore::all();
        usort($users, function($a, $b) {
            $aAdmin = (($a['role'] ?? 'user') === 'admin') ? 0 : 1;
            $bAdmin = (($b['role'] ?? 'user') === 'admin') ? 0 : 1;
            if ($aAdmin !== $bAdmin) return $aAdmin <=> $bAdmin;
            return strcmp(strtolower($a['email'] ?? ''), strtolower($b['email'] ?? ''));
        });
        $this->render('system', 'Настройки — Системни', 'settings-system', ['users'=>$users]);
    }

    private function formulasLocked(): bool {
        $settings = Settings::get();
        return !empty($settings['formulas_locked']);
    }

    public function setFormulasLock(): void {
        Auth::requireAdmin(true);
        $locked = !empty($_POST['locked']);
        $settings = Settings::get();
        $settings['formulas_locked'] = $locked;
        Settings::save($settings);
        Logger::audit($locked ? 'formula.locked' : 'formula.unlocked', ['by' => Auth::user() ?? 'admin']);
        View::json(['ok' => true, 'locked' => $locked]);
    }

    public function restoreFormulaVersion(): void {
        Auth::requireAdmin(true);
        if ($this->formulasLocked()) {
            View::json(['ok' => false, 'error' => 'Формулите са заключени.'], 423);
        }
        $versionId = (int)($_POST['version_id'] ?? 0);
        $result = ProductDB::restoreFormulaVersion($versionId, Auth::user() ?? 'admin');
        View::json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function save(): void {
        Auth::requireAdmin(true);
        $current = Settings::get();
        $tab = $_POST['tab'] ?? 'vat';
        foreach ($_POST as $k => $v) { if ($k !== 'tab') $current[$k] = $v; }
        Settings::save($current);
        Logger::info("Settings saved: tab={$tab}");
        View::json(['success'=>true]);
    }

    public function addColumn(): void {
        Auth::requireAdmin(true);
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['data_type'] ?? 'text');
        $result = ProductDB::addCustomColumn($name, Auth::user() ?? 'admin', $type === 'number' ? 'number' : 'text');
        View::json($result, $result['ok'] ? 200 : 422);
    }

    public function saveFormula(): void {
        Auth::requireAdmin(true);
        if ($this->formulasLocked()) {
            View::json(['ok'=>false,'error'=>'Формулите са заключени.'], 423);
        }
        $column = trim($_POST['column_name'] ?? '');
        $tokens = json_decode((string)($_POST['tokens'] ?? '[]'), true);
        $rounding = (int)($_POST['rounding'] ?? 2);
        $result = ProductDB::saveFormula($column, is_array($tokens) ? $tokens : [], $rounding, Auth::user() ?? 'admin');
        View::json($result, $result['ok'] ? 200 : 422);
    }

    public function clearFormula(): void {
        Auth::requireAdmin(true);
        if ($this->formulasLocked()) {
            View::json(['ok'=>false,'error'=>'Формулите са заключени.'], 423);
        }
        $column = trim($_POST['column_name'] ?? '');
        $result = ProductDB::clearFormula($column, Auth::user() ?? 'admin');
        View::json($result, $result['ok'] ? 200 : 422);
    }


    public function exportFormulasXlsx(): void {
        Auth::requireAdmin();
        $rows = ProductDB::getFormulaExportRows();
        $headers = ['Колона', 'Статус', 'Формула', 'Закръгляне'];
        $xlsx = $this->buildXlsx($headers, $rows, 'Formulas');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="amz-retail-formulas.xlsx"');
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: max-age=0');
        echo $xlsx;
        exit;
    }

    public function downloadFormulaTemplate(): void {
        Auth::requireAdmin();
        $rows = ProductDB::getFormulaExportRows();
        $headers = ['Колона', 'Статус', 'Формула', 'Закръгляне'];
        $xlsx = $this->buildXlsx($headers, $rows, 'Formulas');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="amz-retail-formulas-template.xlsx"');
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: max-age=0');
        echo $xlsx;
        exit;
    }


    public function previewImportFormulas(): void {
        Auth::requireAdmin(true);
        if ($this->formulasLocked()) {
            View::json(['ok'=>false,'error'=>'Формулите са заключени.'], 423);
        }
        if (empty($_FILES['file']) || (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            View::json(['ok'=>false,'error'=>'Не е избран валиден файл.'], 422);
        }
        $file = $_FILES['file'];
        $tmp = (string)$file['tmp_name'];
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            View::json(['ok'=>false,'error'=>'Поддържа се само .xlsx файл за импорт на формули.'], 422);
        }
        $rows = $this->parseFormulaXlsxRows($tmp);
        if (!$rows) {
            View::json(['ok'=>false,'error'=>'Файлът е празен или невалиден.'], 422);
        }
        $result = ProductDB::previewFormulaImportRows($rows);
        View::json($result, 200);
    }

    public function importFormulas(): void {
        Auth::requireAdmin(true);
        if ($this->formulasLocked()) {
            View::json(['ok'=>false,'error'=>'Формулите са заключени.'], 423);
        }
        if (empty($_FILES['file']) || (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            View::json(['ok'=>false,'error'=>'Не е избран валиден файл.'], 422);
        }
        $file = $_FILES['file'];
        $tmp = (string)$file['tmp_name'];
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            View::json(['ok'=>false,'error'=>'Поддържа се само .xlsx файл за импорт на формули.'], 422);
        }
        $rows = $this->parseFormulaXlsxRows($tmp);
        if (!$rows) {
            View::json(['ok'=>false,'error'=>'Файлът е празен или невалиден.'], 422);
        }
        $result = ProductDB::importFormulasFromRows($rows, Auth::user() ?? 'admin');
        View::json($result, $result['ok'] ? 200 : 422);
    }

    private function buildXlsx(array $headers, array $rows, string $sheetName = 'Formulas'): string {
        $sheetName = trim($sheetName);
        $sheetName = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $sheetName);
        $sheetName = preg_replace('/\s+/u', ' ', $sheetName) ?: 'Formulas';
        $sheetName = mb_substr($sheetName, 0, 31);
        if ($sheetName === '') $sheetName = 'Formulas';

        $strings = [];
        $strIndex = [];
        $addString = function(string $s) use (&$strings, &$strIndex): int {
            if (!isset($strIndex[$s])) {
                $strIndex[$s] = count($strings);
                $strings[] = $s;
            }
            return $strIndex[$s];
        };

        $sheetRows = [];
        $hRow = [];
        foreach ($headers as $h) { $hRow[] = ['t' => 's', 'v' => $addString($h)]; }
        $sheetRows[] = $hRow;
        foreach ($rows as $rowData) {
            $row = [];
            foreach ($headers as $h) {
                $val = (string)($rowData[$h] ?? '');
                if ($val !== '' && is_numeric(str_replace(',', '.', $val))) {
                    $row[] = ['t' => 'n', 'v' => str_replace(',', '.', $val)];
                } else {
                    $row[] = ['t' => 's', 'v' => $addString($val)];
                }
            }
            $sheetRows[] = $row;
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($sheetRows as $ri => $row) {
            $rowNum = $ri + 1;
            $sheetXml .= '<row r="' . $rowNum . '">';
            foreach ($row as $ci => $cell) {
                $colNum = $ci + 1;
                $colLetter = '';
                while ($colNum > 0) {
                    $mod = ($colNum - 1) % 26;
                    $colLetter = chr(65 + $mod) . $colLetter;
                    $colNum = intdiv($colNum - 1, 26);
                }
                $ref = $colLetter . $rowNum;
                if ($cell['t'] === 'n') $sheetXml .= '<c r="' . $ref . '"><v>' . htmlspecialchars($cell['v'], ENT_XML1) . '</v></c>';
                else $sheetXml .= '<c r="' . $ref . '" t="s"><v>' . $cell['v'] . '</v></c>';
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData></worksheet>';

        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) $ssXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
        $ssXml .= '</sst>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($sheetName, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>');
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);
        return $content;
    }



    private function parseFormulaXlsxRows(string $path): array {
        if (!is_file($path)) return [];
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return [];
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = @simplexml_load_string($sharedXml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string)$si->t;
                    } else {
                        foreach ($si->r as $run) $text .= (string)$run->t;
                    }
                    $shared[] = $text;
                }
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) return [];
        $sx = @simplexml_load_string($sheetXml);
        if (!$sx) return [];
        $sx->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        $headers = [];
        foreach ($sx->sheetData->row as $r) {
            $cells = [];
            foreach ($r->c as $c) {
                $ref = (string)$c['r'];
                $colLetters = preg_replace('/\d+/', '', $ref);
                $idx = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $idx = $idx * 26 + (ord($colLetters[$i]) - 64);
                }
                $idx--;
                $type = (string)$c['t'];
                $v = isset($c->v) ? (string)$c->v : '';
                if ($type === 's') $value = $shared[(int)$v] ?? '';
                else $value = $v;
                $cells[$idx] = trim((string)$value);
            }
            if (!$cells) continue;
            ksort($cells);
            $max = max(array_keys($cells));
            $line = array_fill(0, $max + 1, '');
            foreach ($cells as $i => $v) $line[$i] = $v;
            if (!$headers) {
                $headers = $line;
                continue;
            }
            if (count(array_filter($line, fn($v) => trim((string)$v) !== '')) === 0) continue;
            $assoc = [];
            foreach ($headers as $i => $h) {
                $key = trim((string)$h);
                if ($key === '') $key = 'col_' . $i;
                $assoc[$key] = $line[$i] ?? '';
            }
            $rows[] = $assoc;
        }
        return $rows;
    }

    public function changeUserRole(): void {
        Auth::requireAdmin(true);
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = trim($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        if ($email === '') View::json(['success'=>false,'error'=>'Липсва имейл.'], 422);
        if ($email === strtolower(Auth::user() ?? '') && $role !== 'admin') {
            View::json(['success'=>false,'error'=>'Не можеш да свалиш собствената си админ роля.'], 422);
        }
        $user = UserStore::findByEmail($email);
        if (!$user) View::json(['success'=>false,'error'=>'Потребителят не е намерен.'], 404);
        if (($user['role'] ?? 'user') === 'admin' && $role === 'user' && UserStore::adminCount() <= 1) {
            View::json(['success'=>false,'error'=>'Трябва да има поне един admin акаунт.'], 422);
        }
        $ok = UserStore::setRole($email, $role);
        if ($ok) Logger::audit('user.role_changed', ['email'=>$email,'role'=>$role,'by'=>Auth::user()]);
        View::json(['success'=>$ok]);
    }
}
