<?php
class CouriersController {
    private function wantsJson(): bool {
        $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        return $xhr === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }
    private array $columns = ['Тегло от','Тегло до','Стойност нетто','Стойност бруто','Австрия','Белгия','Германия','Гърция','Дания','Естония','Ирландия','Испания','Италия','Исландия','Кипър','Латвия','Литва','Люксембург','Малта','Нидерландия','Полша','Португалия','Румъния','Словакия','Словения','Унгария','Финландия','Франция','Хърватия','Чехия','Швеция','UK','USA','Canada','Mexico'];
    public function index(): void {
        Auth::requireAdmin();
        $couriers = ProductDB::getCouriers();
        foreach ($couriers as &$c) {
            $c['imports'] = ProductDB::getCourierRateImports((string)$c['id']);
        }
        unset($c);
        View::renderWithLayout('couriers/index',[
            'pageTitle'=>'Куриери',
            'activePage'=>'couriers',
            'couriers'=>$couriers,
            'importSuccess'=>(int)($_GET['import_ok'] ?? 0) === 1,
            'importCourierId'=>(string)($_GET['courier_id'] ?? ''),
            'importCount'=>(int)($_GET['count'] ?? 0),
            'modeSaved'=>(int)($_GET['mode_ok'] ?? 0) === 1,
            'deleteOk'=>(int)($_GET['delete_ok'] ?? 0) === 1,
            'deleteCourierId'=>(string)($_GET['delete_courier_id'] ?? ''),
            'historyDeleteOk'=>(int)($_GET['history_delete_ok'] ?? 0) === 1,
            'errorMessage'=>(string)($_GET['error'] ?? ''),
        ]);
    }
    public function save(): void {
        Auth::requireAdmin(true);
        $res = ProductDB::saveCourier($_POST);
        if ($this->wantsJson()) {
            View::json(['success'=>!empty($res['ok']),'error'=>$res['error']??null,'id'=>$res['id']??null], !empty($res['ok'])?200:422);
        }
        if (!empty($res['ok'])) {
            View::redirect('/couriers?mode_ok=1');
        }
        http_response_code(422);
        echo $res['error'] ?? 'Грешка при запис на куриер';
    }
    public function template(): void {
        Auth::requireAdmin();
        $candidates = [
            ROOT . '/assets/templates/shipping_prices A1 Courier.xlsx',
            ROOT . '/assets/templates/courier-template.xlsx',
        ];
        foreach ($candidates as $file) {
            if (file_exists($file)) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="shipping_prices A1 Courier.xlsx"');
                header('Content-Length: ' . filesize($file));
                header('Cache-Control: max-age=0');
                readfile($file);
                return;
            }
        }
        $this->downloadA1MatrixTemplate();
    }
    public function export(): void { Auth::requireAdmin(); $id=trim($_GET['id']??''); $courier=ProductDB::getCourier($id); if(!$courier){http_response_code(404); echo 'Невалиден куриер'; return;} $this->downloadRatesXlsx(ProductDB::getCourierRateRows($id), 'courier-rates-'.preg_replace('/[^a-z0-9]+/i','-', strtolower($courier['name'])).'.xlsx'); }
    public function import(): void {
        Auth::requireAdmin(true);
        $id = trim($_POST['courier_id'] ?? '');
        if ($id === '') {
            if ($this->wantsJson()) View::json(['success'=>false,'error'=>'Липсва куриер'],422);
            http_response_code(422); echo 'Липсва куриер'; return;
        }
        if (empty($_FILES['file']) || (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            if ($this->wantsJson()) View::json(['success'=>false,'error'=>'Не е избран валиден файл'],422);
            http_response_code(422); echo 'Не е избран валиден файл'; return;
        }
        $tmp = (string)$_FILES['file']['tmp_name'];
        $ext = strtolower(pathinfo((string)($_FILES['file']['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx','csv'], true)) {
            if ($this->wantsJson()) View::json(['success'=>false,'error'=>'Поддържа се .xlsx или .csv'],422);
            http_response_code(422); echo 'Поддържа се .xlsx или .csv'; return;
        }
        $rows = $this->parseRows($tmp, $ext);
        if (!$rows) {
            if ($this->wantsJson()) View::json(['success'=>false,'error'=>'Файлът е празен или невалиден'],422);
            http_response_code(422); echo 'Файлът е празен или невалиден'; return;
        }
        $fileBlob = (string)@file_get_contents($tmp);
        $mimeType = (string)($_FILES['file']['type'] ?? 'application/octet-stream');
        $origName = (string)($_FILES['file']['name'] ?? 'courier-rates.xlsx');
        $res = ProductDB::importCourierRates($id, $rows, $origName, $mimeType, $fileBlob);
        if ($this->wantsJson()) {
            View::json(['success'=>!empty($res['ok']),'error'=>$res['error']??null,'count'=>$res['count']??0], !empty($res['ok'])?200:422);
        }
        if (!empty($res['ok'])) {
            View::redirect('/couriers?import_ok=1&courier_id=' . urlencode($id) . '&count=' . (int)($res['count'] ?? 0));
        }
        http_response_code(422);
        echo $res['error'] ?? 'Грешка при импорт на цени';
    }
    public function deleteRates(): void {
        Auth::requireAdmin(true);
        $id = trim($_POST['courier_id'] ?? '');
        $confirm1 = trim((string)($_POST['confirm_delete'] ?? ''));
        $confirm2 = trim((string)($_POST['confirm_phrase'] ?? ''));
        if ($confirm1 !== 'YES' || $confirm2 !== 'DELETE') {
            if ($this->wantsJson()) {
                View::json(['success'=>false,'error'=>'Потвърждението за изтриване не е валидно'],422);
            }
            View::redirect('/couriers?error=' . urlencode('Потвърждението за изтриване не е валидно'));
        }
        ProductDB::deleteCourierRates($id);
        if ($this->wantsJson()) {
            View::json(['success'=>true]);
        }
        View::redirect('/couriers?delete_ok=1&delete_courier_id=' . urlencode($id));
    }
    public function activate(): void { Auth::requireAdmin(true); $id=trim($_POST['courier_id']??''); $res = ProductDB::setActiveCourier($id); if (!empty($res['ok'])) { View::redirect('/couriers'); return; } http_response_code(422); echo $res['error'] ?? 'Грешка'; }

    public function deleteCourier(): void {
        Auth::requireAdmin(true);
        $id = trim((string)($_POST['courier_id'] ?? ''));
        $confirm1 = trim((string)($_POST['confirm_delete'] ?? ''));
        $confirm2 = trim((string)($_POST['confirm_phrase'] ?? ''));
        if ($confirm1 !== 'YES' || $confirm2 !== 'DELETE') {
            View::redirect('/couriers?error=' . urlencode('Потвърждението за изтриване на куриер не е валидно'));
        }
        $res = ProductDB::deleteCourier($id);
        if (!empty($res['ok'])) { View::redirect('/couriers?delete_ok=1'); return; }
        View::redirect('/couriers?error=' . urlencode((string)($res['error'] ?? 'Грешка при изтриване на куриер')));
    }

    public function saveMode(): void {
        Auth::requireAdmin(true);
        $mode = trim((string)($_POST['shipping_mode'] ?? 'untracked'));
        if (!in_array($mode, ['untracked','tracked'], true)) $mode = 'untracked';
        $s = Settings::get();
        $s['courier_shipping_mode'] = $mode;
        Settings::save($s);
        View::redirect('/couriers');
    }



    public function deleteImport(): void {
        Auth::requireAdmin(true);
        $id = (int)($_POST['import_id'] ?? 0);
        $courierId = trim((string)($_POST['courier_id'] ?? ''));
        $confirm1 = trim((string)($_POST['confirm_delete'] ?? ''));
        $confirm2 = trim((string)($_POST['confirm_phrase'] ?? ''));
        $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

        if ($confirm1 !== 'YES' || $confirm2 !== 'DELETE') {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success'=>false,'error'=>'Потвърждението за изтриване на файл от историята не е валидно']);
                return;
            }
            View::redirect('/couriers?error=' . urlencode('Потвърждението за изтриване на файл от историята не е валидно'));
        }
        $res = ProductDB::deleteCourierRateImport($id, $courierId);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>!empty($res['ok'])]);
            return;
        }
        if (!empty($res['ok'])) { View::redirect('/couriers?history_delete_ok=1'); return; }
        View::redirect('/couriers?error=' . urlencode('Файлът от историята не беше намерен'));
    }

    public function downloadImport(): void {
        Auth::requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        $row = ProductDB::getCourierRateImport($id);
        if (!$row) {
            http_response_code(404);
            echo 'Невалиден импорт';
            return;
        }
        $mime = trim((string)($row['mime_type'] ?? 'application/octet-stream')) ?: 'application/octet-stream';
        $filename = trim((string)($row['original_filename'] ?? 'courier-rates.xlsx')) ?: 'courier-rates.xlsx';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"','',basename($filename)) . '"');
        header('Content-Length: ' . strlen((string)$row['file_blob']));
        header('Cache-Control: max-age=0');
        echo (string)$row['file_blob'];
        exit;
    }

    private function parseRows(string $path, string $ext): array {
        if ($ext === 'csv') {
            $fh = fopen($path, 'r'); if (!$fh) return [];
            $rows=[]; $headers=[];
            while(($row=fgetcsv($fh,0,';'))!==false){
                if(count($row)===1){$try=str_getcsv($row[0],','); if(count($try)>1) $row=$try;}
                $row=array_map('trim',$row);
                if(!$headers){$headers=$row; continue;}
                $assoc=[]; foreach($headers as $i=>$h){ if($h!=='') $assoc[$h]=$row[$i]??''; }
                if(array_filter($assoc,fn($v)=>$v!=='')) $rows[]=$assoc;
            }
            fclose($fh);
            return $rows;
        }
        $rows = $this->parseA1MatrixXlsx($path);
        if ($rows) return $rows;

        if (class_exists('XlsxParser')) {
            $parsed = XlsxParser::parse($path);
            $matrix = $parsed['products'] ?? [];
            if ($matrix && isset($matrix[0]['Държава'])) {
                return $this->normalizeA1MatrixRows($matrix);
            }
            return $matrix;
        }
        return [];
    }

    private function parseA1MatrixXlsx(string $path): array {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return [];
        $shared = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml !== false) {
            $sx = @simplexml_load_string($xml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    $txt = '';
                    if (isset($si->t)) $txt = (string)$si->t;
                    else foreach ($si->r as $r) $txt .= (string)$r->t;
                    $shared[] = $txt;
                }
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) return [];
        $sx = @simplexml_load_string($sheet);
        if (!$sx || !isset($sx->sheetData)) return [];

        $grid = [];
        $maxCol = 1; $maxRow = 1;
        foreach ($sx->sheetData->row as $r) {
            $ridx = (int)$r['r'];
            $maxRow = max($maxRow, $ridx);
            foreach ($r->c as $c) {
                $ref = (string)$c['r'];
                if (!preg_match('/([A-Z]+)(\d+)/', $ref, $m)) continue;
                $letters = $m[1];
                $idx = 0;
                for ($i=0; $i<strlen($letters); $i++) $idx = $idx*26 + (ord($letters[$i]) - 64);
                $maxCol = max($maxCol, $idx);
                $type = (string)$c['t'];
                $v = isset($c->v) ? (string)$c->v : '';
                $val = $type === 's' ? ($shared[(int)$v] ?? '') : $v;
                $grid[$ridx][$idx] = trim((string)$val);
            }
        }
        if (($grid[3][1] ?? '') !== 'Държава') return [];

        $weights = [];
        $currentGram = null;
        for ($col = 2; $col <= $maxCol; $col++) {
            $top = trim((string)($grid[3][$col] ?? ''));
            if ($top !== '' && preg_match('/^(\d+)\s*гр\.?$/u', $top, $m)) {
                $currentGram = (int)$m[1];
            }
            if ($currentGram === null) continue;
            $mode = trim((string)($grid[4][$col] ?? ''));
            $vat  = trim((string)($grid[5][$col] ?? ''));
            if ($mode === '' || $vat === '') continue;
            $weights[$currentGram][$mode][$vat] = $col;
        }
        if (!$weights) return [];
        ksort($weights);

        $countryRows = [];
        for ($row = 6; $row <= $maxRow; $row++) {
            $country = trim((string)($grid[$row][1] ?? ''));
            if ($country === '') continue;
            $countryRows[$country] = $row;
        }
        if (!$countryRows) return [];

        $result = [];
        $prev = 0.0;
        foreach ($weights as $gram => $defs) {
            $countryMap = [];
            foreach ($countryRows as $country => $rowNum) {
                $countryMap[$country] = [
                    'untracked_net'   => isset($defs['Без проследяване']['Без ДДС']) ? (string)($grid[$rowNum][$defs['Без проследяване']['Без ДДС']] ?? '') : '',
                    'untracked_gross' => isset($defs['Без проследяване']['С ДДС']) ? (string)($grid[$rowNum][$defs['Без проследяване']['С ДДС']] ?? '') : '',
                    'tracked_net'     => isset($defs['С проследяване']['Без ДДС']) ? (string)($grid[$rowNum][$defs['С проследяване']['Без ДДС']] ?? '') : '',
                    'tracked_gross'   => isset($defs['С проследяване']['С ДДС']) ? (string)($grid[$rowNum][$defs['С проследяване']['С ДДС']] ?? '') : '',
                ];
            }
            $to = round($gram / 1000, 4);
            $from = $prev;
            $result[] = [
                'Тегло от' => number_format($from, 4, '.', ''),
                'Тегло до' => number_format($to, 4, '.', ''),
                'Стойност нетто' => '',
                'Стойност бруто' => '',
                '_countries_mode' => $countryMap
            ];
            $prev = $to + 0.0001;
        }
        return $result;
    }

    private function downloadA1MatrixTemplate(): void {
        $file = ROOT . '/assets/templates/shipping_prices A1 Courier.xlsx';
        if (file_exists($file)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="shipping_prices A1 Courier.xlsx"');
            header('Content-Length: ' . filesize($file));
            header('Cache-Control: max-age=0');
            readfile($file);
            exit;
        }
        $this->downloadRatesXlsx([], 'shipping_prices A1 Courier.xlsx');
    }

    private function normalizeA1MatrixRows(array $rows): array {
        if (!$rows) return [];
        $first = $rows[0];
        $countryKeys = array_filter(array_keys($first), fn($k)=> $k !== 'Държава');
        $weights = [];
        foreach ($countryKeys as $k) {
            if (preg_match('/^(\d+)\s*гр\.?(?:\s*\|\s*(Без проследяване|С проследяване)\s*\|\s*(Без ДДС|С ДДС))?$/u', $k, $m)) {
                $gram = (int)$m[1];
                $mode = !empty($m[2]) ? $m[2] : 'Без проследяване';
                $vat = !empty($m[3]) ? $m[3] : 'Без ДДС';
                $weights[$gram][$mode][$vat] = $k;
            }
        }
        ksort($weights);
        $result = [];
        $prev = 0.0;
        foreach ($weights as $gram => $defs) {
            $countryMap = [];
            foreach ($rows as $r) {
                $country = trim((string)($r['Държава'] ?? ''));
                if ($country === '') continue;
                $countryMap[$country] = [
                    'untracked_net'   => isset($defs['Без проследяване']['Без ДДС']) ? (string)($r[$defs['Без проследяване']['Без ДДС']] ?? '') : '',
                    'untracked_gross' => isset($defs['Без проследяване']['С ДДС']) ? (string)($r[$defs['Без проследяване']['С ДДС']] ?? '') : '',
                    'tracked_net'     => isset($defs['С проследяване']['Без ДДС']) ? (string)($r[$defs['С проследяване']['Без ДДС']] ?? '') : '',
                    'tracked_gross'   => isset($defs['С проследяване']['С ДДС']) ? (string)($r[$defs['С проследяване']['С ДДС']] ?? '') : '',
                ];
            }
            $to = round($gram / 1000, 4);
            $from = $prev;
            $result[] = [
                'Тегло от' => number_format($from, 4, '.', ''),
                'Тегло до' => number_format($to, 4, '.', ''),
                'Стойност нетто' => '',
                'Стойност бруто' => '',
                '_countries_mode' => $countryMap
            ];
            $prev = $to + 0.0001;
        }
        return $result;
    }

    private function buildXlsx(array $rows, string $sheetName='CourierRates'): string { $headers=$this->columns; $strings=[];$strIndex=[];$add=function(string $s) use (&$strings,&$strIndex){ if(!isset($strIndex[$s])){$strIndex[$s]=count($strings);$strings[]=$s;} return $strIndex[$s];}; $sheetRows=[]; $hr=[]; foreach($headers as $h)$hr[]=['t'=>'s','v'=>$add($h)]; $sheetRows[]=$hr; foreach($rows as $r){ $row=[]; foreach($headers as $h){ $v=(string)($r[$h]??''); if($v!==''&&is_numeric(str_replace(',','.',$v))) $row[]=['t'=>'n','v'=>str_replace(',','.',$v)]; else $row[]=['t'=>'s','v'=>$add($v)]; } $sheetRows[]=$row; } $sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'; foreach($sheetRows as $ridx=>$cells){ $sheet.='<row r="'.($ridx+1).'">'; foreach($cells as $cidx=>$cell){ $ref=$this->cellRef($cidx,$ridx+1); $sheet.= $cell['t']==='n' ? '<c r="'.$ref.'"><v>'.$cell['v'].'</v></c>' : '<c r="'.$ref.'" t="s"><v>'.$cell['v'].'</v></c>'; } $sheet.='</row>'; } $sheet.='</sheetData></worksheet>'; $ss='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">'; foreach($strings as $s)$ss.='<si><t>'.htmlspecialchars($s, ENT_XML1|ENT_QUOTES, 'UTF-8').'</t></si>'; $ss.='</sst>'; $tmp=tempnam(sys_get_temp_dir(),'xlsx_'); $zip=new ZipArchive(); $zip->open($tmp, ZipArchive::OVERWRITE); $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>'); $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'); $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.htmlspecialchars($sheetName, ENT_XML1).'" sheetId="1" r:id="rId1"/></sheets></workbook>'); $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'); $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts><font><sz val="11"/><name val="Calibri"/></font></fonts><fills><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>'); $zip->addFromString('xl/sharedStrings.xml',$ss); $zip->addFromString('xl/worksheets/sheet1.xml',$sheet); $zip->close(); $content=file_get_contents($tmp); @unlink($tmp); return $content; }
    private function downloadRatesXlsx(array $rows, string $filename): void { $xlsx=$this->buildXlsx($rows); header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="'.$filename.'"'); header('Content-Length: '.strlen($xlsx)); header('Cache-Control: max-age=0'); echo $xlsx; exit; }
    private function cellRef(int $c, int $r): string { $n=$c+1;$s='';while($n>0){$m=($n-1)%26;$s=chr(65+$m).$s;$n=(int)(($n-$m-1)/26);}return $s.$r; }
}
