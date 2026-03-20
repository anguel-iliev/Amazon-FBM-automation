<?php
$users   = $users ?? [];
$smtpOk  = $smtpOk ?? false;
$admins  = array_filter($users, function($u) { return ($u['role'] ?? '') === 'admin'; });
$regular = array_filter($users, function($u) { return ($u['role'] ?? '') !== 'admin'; });
$me      = Auth::user();
?>

<?php if (!$smtpOk): ?>
<div style="background:rgba(224,92,92,0.10);border:1px solid rgba(224,92,92,0.35);border-radius:6px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:flex-start;gap:12px">
  <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px"><circle cx="10" cy="10" r="8.5" stroke="#E05C5C" stroke-width="1.6"/><path d="M10 6v5M10 13.5v.5" stroke="#E05C5C" stroke-width="1.8" stroke-linecap="round"/></svg>
  <div>
    <div style="font-size:13px;font-weight:600;color:#F5A0A0;margin-bottom:4px">SMTP не е конфигуриран</div>
    <div style="font-size:12px;color:rgba(245,160,160,0.75);line-height:1.6">
      Поканите няма да се изпращат без Gmail App Password.<br>
      Редактирай <code style="color:#E05C5C">.env</code> → <code style="color:#E05C5C">SMTP_PASS=xxxxxxxxxxxxxxxxxxxx</code><br>
      или стартирай <code style="color:#E05C5C">php setup.php</code> за интерактивна конфигурация.
    </div>
  </div>
</div>
<?php endif; ?>

<div class="grid-2" style="align-items:start">
  <!-- Invite form -->
  <div class="card">
    <div class="card-title">Покани нов потребител</div>
    <p class="text-sm text-muted" style="margin-bottom:20px;line-height:1.7">
      Въведете имейл адреса. Потребителят ще получи линк за активиране и ще зададе парола сам.
    </p>
    <?php $err = Session::getFlash('error'); if ($err): ?>
    <div class="flash flash-error" style="margin-bottom:16px"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <?php $ok = Session::getFlash('success'); if ($ok): ?>
    <div class="flash flash-success" style="margin-bottom:16px"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>
    <form method="POST" action="/invite">
      <div class="form-group">
        <label class="form-label">Имейл адрес</label>
        <input type="email" name="email" class="form-control" placeholder="user@example.com" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:4px" <?= !$smtpOk ? 'title="Внимание: SMTP_PASS не е зададен"' : '' ?>>
        <?= !$smtpOk ? '⚠ ' : '' ?>Изпрати покана
      </button>
    </form>
  </div>

  <!-- Info -->
  <div class="card">
    <div class="card-title">Как работи</div>
    <ol style="list-style:none;display:flex;flex-direction:column;gap:12px">
      <?php foreach ([
        'Въведи имейла на новия потребител',
        'Той получава имейл с линк за активиране (валиден 24 часа)',
        'Отваря линка и задава своя парола',
        'Влиза в платформата с имейл и парола',
      ] as $i => $step): ?>
      <li style="display:flex;gap:10px;align-items:flex-start">
        <span style="width:20px;height:20px;background:rgba(201,168,76,0.12);border:1px solid rgba(201,168,76,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold);flex-shrink:0"><?= $i+1 ?></span>
        <span class="text-sm text-muted"><?= $step ?></span>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</div>

<!-- Users table -->
<div class="card mt-16" style="padding:0">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
    <div class="card-title" style="margin:0">Потребители (<?= count($users) ?>)</div>
    <span class="text-sm text-muted"><?= count(array_filter($users, function($u) { return !empty($u['verified']); })) ?> активни</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Имейл</th><th>Роля</th><th>Статус</th><th>Поканен от</th><th>Последен вход</th><th>Регистриран</th><th style="text-align:right">Действия</th></tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--muted)">Няма потребители</td></tr>
        <?php else: ?>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge <?= ($u['role']??'')=='admin' ? 'badge-gold' : 'badge-muted' ?>"><?= $u['role'] ?? 'user' ?></span></td>
          <td>
            <?php if ($u['verified'] ?? false): ?>
            <span class="badge badge-green">Активен</span>
            <?php else: ?>
            <span class="badge badge-muted">Чака активиране</span>
            <?php endif; ?>
          </td>
          <td class="text-sm text-muted"><?= htmlspecialchars($u['invited_by'] ?? '—') ?></td>
          <td class="text-sm text-muted"><?= $u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : '—' ?></td>
          <td class="text-sm text-muted"><?= date('d.m.Y', strtotime($u['created_at'] ?? 'now')) ?></td>
          <td style="text-align:right;white-space:nowrap">
            <?php if (!($u['verified'] ?? false)): ?>
            <!-- Resend invite -->
            <form method="POST" action="/invite/resend" style="display:inline">
              <input type="hidden" name="email" value="<?= htmlspecialchars($u['email']) ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                      title="Изпрати повторно"
                      style="font-size:11px;padding:4px 10px"
                      onclick="return confirm('Изпрати покана повторно до <?= htmlspecialchars($u['email']) ?>?')">
                ↺ Повторно
              </button>
            </form>
            <?php endif; ?>
            <?php if (strtolower($u['email']) !== strtolower($me)): ?>
            <!-- Delete -->
            <form method="POST" action="/invite/delete" style="display:inline;margin-left:4px">
              <input type="hidden" name="email" value="<?= htmlspecialchars($u['email']) ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                      title="Изтрий потребител"
                      style="font-size:11px;padding:4px 10px;color:var(--red,#E05C5C)"
                      onclick="return confirm('Изтрий <?= htmlspecialchars($u['email']) ?>? Това действие е необратимо.')">
                ✕ Изтрий
              </button>
            </form>
            <?php else: ?>
            <span class="text-sm text-muted" style="font-size:11px">(аз)</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
