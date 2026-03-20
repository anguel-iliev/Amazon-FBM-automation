<?php
$users   = $users ?? [];
$admins  = array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin');
$regular = array_filter($users, fn($u) => ($u['role'] ?? '') !== 'admin');
?>
<div class="page-header">
  <div></div>
</div>

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
      <button type="submit" class="btn btn-primary" style="margin-top:4px">
        Изпрати покана
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
  <div style="padding:16px 20px;border-bottom:1px solid var(--border)">
    <div class="card-title" style="margin:0">Потребители (<?= count($users) ?>)</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Имейл</th><th>Роля</th><th>Статус</th><th>Поканен от</th><th>Последен вход</th><th>Регистриран</th></tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--muted)">Няма потребители</td></tr>
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
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
