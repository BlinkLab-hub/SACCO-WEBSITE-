<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
require_staff($user);
$db = db();

// Last six months, built from the database.
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("first day of -{$i} month");
    $months[date('Y-m', $ts)] = ['label' => date('M', $ts), 'savings' => 0, 'loans' => 0];
}
foreach ($db->query("SELECT paid_on, amount FROM contributions WHERE type IN ('Savings','Shares')")->fetchAll() as $row) {
    $key = substr($row['paid_on'], 0, 7);
    if (isset($months[$key])) {
        $months[$key]['savings'] += (int)$row['amount'];
    }
}
foreach ($db->query('SELECT issued_on, amount FROM loans')->fetchAll() as $row) {
    $key = substr($row['issued_on'], 0, 7);
    if (isset($months[$key])) {
        $months[$key]['loans'] += (int)$row['amount'];
    }
}
$peak = max([1, ...array_map(fn($m) => max($m['savings'], $m['loans']), $months)]);

$totalMembers = (int)$db->query('SELECT COUNT(*) FROM members')->fetchColumn();
$overdueMembers = (int)$db->query("SELECT COUNT(*) FROM members WHERE status = 'Overdue'")->fetchColumn();
$totalSavings = (int)$db->query("SELECT COALESCE(SUM(amount),0) FROM contributions WHERE type IN ('Savings','Shares')")->fetchColumn();
$outstanding = (int)$db->query("SELECT COALESCE(SUM(balance),0) FROM loans WHERE status <> 'Cleared'")->fetchColumn();
$onTime = $totalMembers > 0 ? (int)round(($totalMembers - $overdueMembers) / $totalMembers * 100) : 0;

$memberCounts = [];
foreach ($db->query('SELECT status, COUNT(*) AS n FROM members GROUP BY status')->fetchAll() as $row) {
    $memberCounts[$row['status']] = (int)$row['n'];
}
$loanCounts = [];
foreach ($db->query('SELECT status, COUNT(*) AS n FROM loans GROUP BY status')->fetchAll() as $row) {
    $loanCounts[$row['status']] = (int)$row['n'];
}

render_top('Analytics', 'analytics', $user);
?>
<?= page_header('Analytics', 'Group performance at a glance.') ?>

<section class="cards">
    <article class="card"><span class="card-label">Members</span><strong class="card-value"><?= $totalMembers ?></strong></article>
    <article class="card"><span class="card-label">Total savings</span><strong class="card-value"><?= ugx($totalSavings) ?></strong></article>
    <article class="card"><span class="card-label">Loans outstanding</span><strong class="card-value"><?= ugx($outstanding) ?></strong></article>
    <article class="card"><span class="card-label">Members not overdue</span><strong class="card-value"><?= $onTime ?>%</strong></article>
</section>

<section class="panel">
    <h2>Monthly savings vs loans issued</h2>
    <div class="chart" role="img" aria-label="Monthly savings and loans chart">
        <?php foreach ($months as $m): ?>
            <div class="group">
                <div class="bars">
                    <div class="bar savings" title="Savings: <?= e(ugx($m['savings'])) ?>" style="height:<?= (int)round($m['savings'] / $peak * 100) ?>%"></div>
                    <div class="bar loans" title="Loans: <?= e(ugx($m['loans'])) ?>" style="height:<?= (int)round($m['loans'] / $peak * 100) ?>%"></div>
                </div>
                <span class="label"><?= e($m['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="chart-legend">
        <span><span class="legend-dot" style="background:var(--blue)"></span>Savings</span>
        <span><span class="legend-dot" style="background:var(--orange)"></span>Loans issued</span>
    </div>
</section>

<section class="grid-2">
    <article class="panel">
        <h2>Member status</h2>
        <div class="bar-list"><?= bar_list(['Active' => $memberCounts['Active'] ?? 0, 'Overdue' => $memberCounts['Overdue'] ?? 0, 'Inactive' => $memberCounts['Inactive'] ?? 0]) ?></div>
    </article>
    <article class="panel">
        <h2>Loan status</h2>
        <div class="bar-list"><?= bar_list(['Active' => $loanCounts['Active'] ?? 0, 'Overdue' => $loanCounts['Overdue'] ?? 0, 'Pending' => $loanCounts['Pending'] ?? 0, 'Cleared' => $loanCounts['Cleared'] ?? 0]) ?></div>
    </article>
</section>
<?php
render_bottom();
