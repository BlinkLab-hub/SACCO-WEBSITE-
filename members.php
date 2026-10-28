<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
require_staff($user);
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? 'All');
if (!in_array($status, ['All', 'Active', 'Overdue', 'Inactive'], true)) {
    $status = 'All';
}

$params = [':q1' => '%' . $q . '%', ':q2' => '%' . $q . '%', ':q3' => '%' . $q . '%'];
$statusSql = '';
if ($status !== 'All') {
    $statusSql = ' AND m.status = :status';
    $params[':status'] = $status;
}
$stmt = $db->prepare(
    "SELECT m.id, m.member_no, m.full_name, m.phone, m.joined, m.status,
        (SELECT COALESCE(SUM(c.amount),0) FROM contributions c
            WHERE c.member_id = m.id AND c.type IN ('Savings','Shares')) AS savings
     FROM members m
     WHERE (m.full_name LIKE :q1 OR m.member_no LIKE :q2 OR m.phone LIKE :q3){$statusSql}
     ORDER BY m.member_no"
);
$stmt->execute($params);
$members = $stmt->fetchAll();

render_top('Members', 'members', $user);
?>
<?= page_header(
    'Members',
    'All registered sacco members.',
    '<button class="btn primary" type="button" data-toggle="#add-member-form">Add member</button>'
) ?>

<section class="panel hidden" id="add-member-form">
    <h2>New member</h2>
    <form class="form" method="post" action="action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="back" value="members.php">
        <label class="field">Full name
            <input type="text" name="full_name" required>
        </label>
        <label class="field">Phone (e.g. 0772 110 230)
            <input type="tel" name="phone" required>
        </label>
        <label class="field">Email (optional)
            <input type="email" name="email">
        </label>
        <label class="field">Date joined
            <input type="date" name="joined" value="<?= e(today()) ?>" required>
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Save member</button>
        </div>
    </form>
</section>

<section class="panel">
    <form method="get" action="members.php" class="toolbar">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, member number or phone" aria-label="Search members">
        <select name="status" aria-label="Filter by status">
            <?php foreach (['All', 'Active', 'Overdue', 'Inactive'] as $option): ?>
                <option<?= $option === $status ? ' selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Search</button>
    </form>
    <p class="muted small">Showing <?= count($members) ?> member<?= count($members) === 1 ? '' : 's' ?></p>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>No.</th><th>Name</th><th>Phone</th><th>Joined</th>
                    <th class="num">Savings</th><th>Status</th><th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= e($m['member_no']) ?></td>
                        <td><?= e($m['full_name']) ?></td>
                        <td><?= e($m['phone']) ?></td>
                        <td><?= e(fmt_date($m['joined'])) ?></td>
                        <td class="num"><?= ugx($m['savings']) ?></td>
                        <td><?= status_badge($m['status']) ?></td>
                        <td class="row-action">
                            <a class="btn small" href="member.php?id=<?= (int)$m['id'] ?>">View details</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$members): ?>
                    <tr><td colspan="7" class="empty">No members match your search.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_bottom();
