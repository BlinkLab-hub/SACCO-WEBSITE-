<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$db = db();
$staff = is_staff($user);
[$scope, $scopeParams] = member_scope($user, 'l');

$stmt = $db->prepare(
    "SELECT l.*, m.full_name FROM loans l
     JOIN members m ON m.id = l.member_id
     WHERE {$scope}
     ORDER BY l.id DESC"
);
$stmt->execute($scopeParams);
$loans = $stmt->fetchAll();

$counts = ['active' => 0, 'outstanding' => 0, 'pending' => 0, 'overdue' => 0];
foreach ($loans as $l) {
    if (in_array($l['status'], ['Active', 'Overdue'], true)) {
        $counts['active']++;
    }
    if ($l['status'] !== 'Cleared') {
        $counts['outstanding'] += (int)$l['balance'];
    }
    if ($l['status'] === 'Pending') {
        $counts['pending']++;
    }
    if ($l['status'] === 'Overdue') {
        $counts['overdue']++;
    }
}

$members = $staff ? $db->query('SELECT id, full_name FROM members ORDER BY full_name')->fetchAll() : [];

render_top('Loans', 'loans', $user);
?>
<?= page_header(
    'Loans',
    $staff ? 'Loan applications, balances and repayments for all members.' : 'Your loans and repayments.',
    '<button class="btn primary" type="button" data-toggle="#loan-panel">Apply for loan</button>'
) ?>

<section class="cards">
    <article class="card"><span class="card-label">Active loans</span><strong class="card-value"><?= $counts['active'] ?></strong></article>
    <article class="card"><span class="card-label">Outstanding balance</span><strong class="card-value"><?= ugx($counts['outstanding']) ?></strong></article>
    <article class="card"><span class="card-label">Pending applications</span><strong class="card-value"><?= $counts['pending'] ?></strong></article>
    <article class="card"><span class="card-label">Overdue</span><strong class="card-value"><?= $counts['overdue'] ?></strong></article>
</section>

<section class="panel hidden" id="loan-panel">
    <h2>New loan application</h2>
    <form class="form" method="post" action="action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="apply_loan">
        <input type="hidden" name="back" value="loans.php">
        <?php if ($staff): ?>
            <label class="field">Member
                <select name="member_id">
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label class="field">Amount (UGX)
            <input type="number" name="amount" min="10000" step="10000" required>
        </label>
        <label class="field">Repayment period (months)
            <select name="months">
                <option>3</option><option>6</option><option selected>12</option><option>24</option>
            </select>
        </label>
        <label class="field">Purpose
            <input type="text" name="purpose" placeholder="e.g. school fees" required>
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Submit application</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Loan register</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Loan</th><th>Member</th><th class="num">Amount</th><th class="num">Balance</th>
                    <th>Due</th><th>Repaid</th><th>Status</th><?php if ($staff): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $l):
                    $repaid = $l['amount'] > 0 ? (int)round(($l['amount'] - $l['balance']) / $l['amount'] * 100) : 0; ?>
                    <tr>
                        <td><?= e($l['loan_no']) ?></td>
                        <td><?= e($l['full_name']) ?></td>
                        <td class="num"><?= ugx($l['amount']) ?></td>
                        <td class="num"><?= ugx($l['balance']) ?></td>
                        <td><?= e(fmt_date($l['due'])) ?></td>
                        <td style="min-width:120px">
                            <div class="progress" role="img" aria-label="<?= $repaid ?>% repaid"><span style="width:<?= $repaid ?>%"></span></div>
                            <span class="small muted"><?= $repaid ?>%</span>
                        </td>
                        <td><?= status_badge($l['status']) ?></td>
                        <?php if ($staff): ?>
                            <td class="row-action">
                                <?php if ($l['status'] === 'Pending'): ?>
                                    <form method="post" action="action.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="approve_loan">
                                        <input type="hidden" name="back" value="loans.php">
                                        <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>">
                                        <button class="btn small primary" type="submit">Approve</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$loans): ?>
                    <tr><td colspan="<?= $staff ? 8 : 7 ?>" class="empty">No loans yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_bottom();
