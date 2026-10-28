<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$db = db();
$id = (int)($_GET['id'] ?? 0);

// Members may only open their own record. Staff can open any record.
if (!is_staff($user) && $id !== (int)($user['member_id'] ?? 0)) {
    forbid('Members can only view their own details.');
}

$member = fetch_one('SELECT * FROM members WHERE id = ?', [$id]);
if (!$member) {
    error_page(404, 'Member not found', 'This member record does not exist.');
}

$totals = member_totals($id);
$contributions = $db->prepare('SELECT * FROM contributions WHERE member_id = ? ORDER BY paid_on DESC, id DESC LIMIT 50');
$contributions->execute([$id]);
$contributions = $contributions->fetchAll();

$loans = $db->prepare('SELECT * FROM loans WHERE member_id = ? ORDER BY id DESC');
$loans->execute([$id]);
$loans = $loans->fetchAll();

$activeLoan = null;
foreach ($loans as $loan) {
    if (in_array($loan['status'], ['Active', 'Overdue'], true)) {
        $activeLoan = $loan;
        break;
    }
}

$staff = is_staff($user);
render_top($member['full_name'], 'members', $user);
?>
<?= page_header(
    $member['full_name'],
    $member['member_no'] . ' · joined ' . fmt_date($member['joined']),
    '<a class="btn" href="' . ($staff ? 'members.php' : 'dashboard.php') . '">Back</a>'
) ?>

<section class="cards">
    <article class="card">
        <span class="card-label">Total savings</span>
        <strong class="card-value"><?= ugx($totals['savings']) ?></strong>
        <span class="card-note">Savings and shares</span>
    </article>
    <article class="card">
        <span class="card-label">Shares</span>
        <strong class="card-value"><?= (int)$totals['share_units'] ?> shares</strong>
        <span class="card-note"><?= ugx($totals['shares']) ?></span>
    </article>
    <article class="card">
        <span class="card-label">Active loan balance</span>
        <strong class="card-value"><?= $activeLoan ? ugx($activeLoan['balance']) : 'UGX 0' ?></strong>
        <span class="card-note"><?= $activeLoan ? e($activeLoan['loan_no']) : 'No active loan' ?></span>
    </article>
    <article class="card">
        <span class="card-label">Status</span>
        <strong class="card-value"><?= status_badge($member['status']) ?></strong>
        <span class="card-note"><?= e($member['member_no']) ?></span>
    </article>
</section>

<section class="panel">
    <h2>Contact details</h2>
    <dl class="details">
        <div><dt>Phone</dt><dd><?= e($member['phone']) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($member['email'] ?: '—') ?></dd></div>
        <div><dt>Member number</dt><dd><?= e($member['member_no']) ?></dd></div>
        <div><dt>Date joined</dt><dd><?= e(fmt_date($member['joined'])) ?></dd></div>
    </dl>
</section>

<?php if ($staff): ?>
<section class="panel">
    <h2>Record a contribution for <?= e($member['full_name']) ?></h2>
    <form class="form" method="post" action="action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="record_contribution">
        <input type="hidden" name="back" value="member.php?id=<?= (int)$id ?>">
        <input type="hidden" name="member_id" value="<?= (int)$id ?>">
        <label class="field">Amount (UGX)
            <input type="number" name="amount" min="1000" step="500" required>
        </label>
        <label class="field">Type
            <select name="type">
                <option>Savings</option><option>Shares</option><option>Welfare</option>
            </select>
        </label>
        <label class="field">Method
            <select name="method">
                <option>MTN MoMo</option><option>Airtel Money</option><option>Bank</option><option>Cash</option>
            </select>
        </label>
        <label class="field">Date
            <input type="date" name="paid_on" value="<?= e(today()) ?>" required>
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Save contribution</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Contributions</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Type</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead>
            <tbody>
                <?php foreach ($contributions as $c): ?>
                    <tr>
                        <td><?= e(fmt_date($c['paid_on'])) ?></td>
                        <td><?= e($c['type']) ?></td>
                        <td><?= e($c['method']) ?></td>
                        <td><?= e($c['ref']) ?></td>
                        <td class="num"><?= ugx($c['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$contributions): ?>
                    <tr><td colspan="5" class="empty">No contributions yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Loans</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Loan</th><th>Purpose</th><th class="num">Amount</th><th class="num">Balance</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($loans as $l): ?>
                    <tr>
                        <td><?= e($l['loan_no']) ?></td>
                        <td><?= e($l['purpose']) ?></td>
                        <td class="num"><?= ugx($l['amount']) ?></td>
                        <td class="num"><?= ugx($l['balance']) ?></td>
                        <td><?= e(fmt_date($l['due'])) ?></td>
                        <td><?= status_badge($l['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$loans): ?>
                    <tr><td colspan="6" class="empty">No loans yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_bottom();
