<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$db = db();
$staff = is_staff($user);
[$scope, $scopeParams] = member_scope($user, 'c');

$stmt = $db->prepare(
    "SELECT c.*, m.full_name FROM contributions c
     JOIN members m ON m.id = c.member_id
     WHERE {$scope}
     ORDER BY c.paid_on DESC, c.id DESC LIMIT 200"
);
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

$totals = $db->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN c.type IN ('Savings','Shares') THEN c.amount ELSE 0 END),0) AS total,
        COALESCE(SUM(CASE WHEN c.type IN ('Savings','Shares') AND c.paid_on LIKE :month THEN c.amount ELSE 0 END),0) AS month,
        COALESCE(SUM(CASE WHEN c.type = 'Shares' THEN c.amount ELSE 0 END),0) AS shares
     FROM contributions c WHERE {$scope}"
);
$totals->execute([':month' => date('Y-m') . '%'] + $scopeParams);
$totals = $totals->fetch();

$members = $staff ? $db->query('SELECT id, full_name FROM members ORDER BY full_name')->fetchAll() : [];

render_top('Savings', 'savings', $user);
?>
<?= page_header(
    'Savings',
    $staff ? 'Contributions, shares and welfare payments for all members.' : 'Your contributions, shares and welfare payments.',
    $staff ? '<button class="btn primary" type="button" data-toggle="#contrib-panel">Record contribution</button>' : ''
) ?>

<section class="cards">
    <article class="card"><span class="card-label">Total savings and shares</span><strong class="card-value"><?= ugx($totals['total']) ?></strong></article>
    <article class="card"><span class="card-label">Collected this month</span><strong class="card-value"><?= ugx($totals['month']) ?></strong></article>
    <article class="card"><span class="card-label">Shares value</span><strong class="card-value"><?= ugx($totals['shares']) ?></strong></article>
</section>

<?php if ($staff): ?>
<section class="panel hidden" id="contrib-panel">
    <h2>Record a contribution</h2>
    <form class="form" method="post" action="action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="record_contribution">
        <input type="hidden" name="back" value="savings.php">
        <label class="field">Member
            <select name="member_id">
                <?php foreach ($members as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
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
    <h2>Contribution history</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Member</th><th>Type</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(fmt_date($r['paid_on'])) ?></td>
                        <td><?= e($r['full_name']) ?></td>
                        <td><?= e($r['type']) ?></td>
                        <td><?= e($r['method']) ?></td>
                        <td><?= e($r['ref']) ?></td>
                        <td class="num"><?= ugx($r['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="empty">No contributions yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_bottom();
