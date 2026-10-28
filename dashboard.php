<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$db = db();
$staff = is_staff($user);

if ($staff) {
    $stats = fetch_one(
        "SELECT
            (SELECT COUNT(*) FROM members) AS members,
            (SELECT COALESCE(SUM(amount),0) FROM contributions WHERE type IN ('Savings','Shares')) AS savings,
            (SELECT COALESCE(SUM(balance),0) FROM loans WHERE status <> 'Cleared') AS outstanding,
            (SELECT COUNT(*) FROM loans WHERE status <> 'Cleared') AS open_loans,
            (SELECT COALESCE(SUM(amount),0) FROM contributions WHERE paid_on = ?) AS collected_today",
        [today()]
    );
    $attention = $db->query(
        "SELECT l.loan_no, l.status, m.full_name FROM loans l
         JOIN members m ON m.id = l.member_id
         WHERE l.status IN ('Overdue','Pending') ORDER BY l.status, l.id"
    )->fetchAll();
    $recent = $db->query(
        "SELECT c.paid_on, c.type, c.amount, m.full_name FROM contributions c
         JOIN members m ON m.id = c.member_id
         ORDER BY c.paid_on DESC, c.id DESC LIMIT 5"
    )->fetchAll();
} else {
    $memberId = (int)($user['member_id'] ?? 0);
    $member = fetch_one('SELECT * FROM members WHERE id = ?', [$memberId]);
    $totals = member_totals($memberId);
    $loan = fetch_one(
        "SELECT * FROM loans WHERE member_id = ? AND status IN ('Active','Overdue') ORDER BY id LIMIT 1",
        [$memberId]
    );
    $upto = (string)($_GET['upto'] ?? '');
    if (!valid_date($upto)) {
        $upto = today();
    }
    $stmt = $db->prepare(
        "SELECT paid_on, type, method, amount FROM contributions
         WHERE member_id = ? AND paid_on <= ? ORDER BY paid_on DESC, id DESC LIMIT 5"
    );
    $stmt->execute([$memberId, $upto]);
    $recent = $stmt->fetchAll();
    $unread = unread_count($user);
    $pending = fetch_one("SELECT COUNT(*) AS n FROM loans WHERE member_id = ? AND status = 'Pending'", [$memberId]);
}

render_top('Dashboard', 'dashboard', $user);

if ($staff): ?>
    <?= page_header('Dashboard', 'Sacco overview for ' . $user['full_name'] . '.') ?>

    <section class="cards">
        <article class="card">
            <span class="card-label">Members</span>
            <strong class="card-value"><?= (int)$stats['members'] ?></strong>
            <span class="card-note">Registered in the sacco</span>
        </article>
        <article class="card">
            <span class="card-label">Total savings</span>
            <strong class="card-value"><?= ugx($stats['savings']) ?></strong>
            <span class="card-note">Savings and shares, all members</span>
        </article>
        <article class="card">
            <span class="card-label">Loans outstanding</span>
            <strong class="card-value"><?= ugx($stats['outstanding']) ?></strong>
            <span class="card-note"><?= (int)$stats['open_loans'] ?> open loans</span>
        </article>
        <article class="card">
            <span class="card-label">Collected today</span>
            <strong class="card-value"><?= ugx($stats['collected_today']) ?></strong>
            <span class="card-note">Recorded contributions</span>
        </article>
    </section>

    <section class="grid-2">
        <article class="panel">
            <h2>Needs attention</h2>
            <ul class="list">
                <?php foreach ($attention as $item): ?>
                    <li class="alert<?= $item['status'] === 'Overdue' ? ' warn' : '' ?>">
                        <?= e($item['full_name']) ?>:
                        loan <?= e($item['loan_no']) ?> is
                        <?= $item['status'] === 'Overdue' ? 'overdue' : 'awaiting approval' ?>.
                    </li>
                <?php endforeach; ?>
                <?php if (!$attention): ?>
                    <li class="alert">Nothing needs attention right now.</li>
                <?php endif; ?>
            </ul>
        </article>
        <article class="panel">
            <h2>Quick admin actions</h2>
            <div class="quick-actions">
                <a class="btn primary" href="members.php">Review members</a>
                <a class="btn" href="loans.php">Review loans</a>
                <a class="btn" href="savings.php">Record collection</a>
                <a class="btn" href="reports.php">Export reports</a>
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Recent collections (all members)</h2>
            <a href="savings.php">View all</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Member</th><th>Type</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= e(fmt_date($row['paid_on'])) ?></td>
                            <td><?= e($row['full_name']) ?></td>
                            <td><?= e($row['type']) ?></td>
                            <td class="num"><?= ugx($row['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <tr><td colspan="4" class="empty">No collections recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php else: ?>
    <?= page_header('Dashboard', 'Welcome back, ' . ($member['full_name'] ?? $user['full_name']) . '. Here is your position today.') ?>

    <section class="cards">
        <article class="card">
            <span class="card-label">Total savings</span>
            <strong class="card-value"><?= ugx($totals['savings']) ?></strong>
            <span class="card-note">Savings and shares combined</span>
        </article>
        <article class="card">
            <span class="card-label">Shares held</span>
            <strong class="card-value"><?= (int)$totals['share_units'] ?> shares</strong>
            <span class="card-note">Worth <?= ugx($totals['shares']) ?></span>
        </article>
        <article class="card">
            <span class="card-label">Active loan balance</span>
            <?php if ($loan): $repaid = $loan['amount'] > 0 ? (int)round(($loan['amount'] - $loan['balance']) / $loan['amount'] * 100) : 0; ?>
                <strong class="card-value"><?= ugx($loan['balance']) ?></strong>
                <div class="progress" role="img" aria-label="<?= $repaid ?>% repaid"><span style="width:<?= $repaid ?>%"></span></div>
                <span class="card-note"><?= $repaid ?>% of <?= e($loan['loan_no']) ?> repaid</span>
            <?php else: ?>
                <strong class="card-value">UGX 0</strong>
                <span class="card-note">No active loan</span>
            <?php endif; ?>
        </article>
        <article class="card">
            <span class="card-label">Next repayment</span>
            <?php if ($loan): ?>
                <strong class="card-value"><?= ugx($loan['monthly_payment']) ?></strong>
                <span class="card-note">Due <?= e(fmt_date($loan['due'])) ?></span>
            <?php else: ?>
                <strong class="card-value">—</strong>
                <span class="card-note">Nothing due</span>
            <?php endif; ?>
        </article>
    </section>

    <section class="grid-2">
        <article class="panel">
            <h2>Quick actions</h2>
            <div class="quick-actions">
                <a class="btn primary" href="savings.php">View savings</a>
                <a class="btn" href="loans.php">Apply for loan</a>
                <a class="btn" href="messages.php">Message the sacco</a>
                <a class="btn" href="member.php?id=<?= (int)$user['member_id'] ?>">My details</a>
            </div>
        </article>
        <article class="panel">
            <h2>Alerts</h2>
            <ul class="list">
                <?php if ($loan && $loan['status'] === 'Overdue'): ?>
                    <li class="alert warn">Loan <?= e($loan['loan_no']) ?> is overdue. Please contact the treasurer.</li>
                <?php elseif ($loan): ?>
                    <li class="alert warn">Loan <?= e($loan['loan_no']) ?> repayment of <?= ugx($loan['monthly_payment']) ?> is due <?= e(fmt_date($loan['due'])) ?>.</li>
                <?php endif; ?>
                <?php if ((int)$pending['n'] > 0): ?>
                    <li class="alert">Your loan application is waiting for approval.</li>
                <?php endif; ?>
                <?php if ($unread > 0): ?>
                    <li class="alert"><a href="messages.php">You have <?= $unread ?> unread message<?= $unread === 1 ? '' : 's' ?>.</a></li>
                <?php endif; ?>
                <?php if (!$loan && (int)$pending['n'] === 0 && $unread === 0): ?>
                    <li class="alert">No alerts. Everything is up to date.</li>
                <?php endif; ?>
            </ul>
        </article>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Recent transactions</h2>
            <form method="get" action="dashboard.php" class="inline-form">
                <label class="small">Show up to
                    <input type="date" name="upto" value="<?= e($upto) ?>">
                </label>
                <button class="btn small" type="submit">Show</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Type</th><th>Method</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= e(fmt_date($row['paid_on'])) ?></td>
                            <td><?= e($row['type']) ?></td>
                            <td><?= e($row['method']) ?></td>
                            <td class="num"><?= ugx($row['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <tr><td colspan="4" class="empty">No transactions up to this date.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

    <section class="panel">
        <h2>Announcements</h2>
        <ul class="list">
            <li><strong>Annual General Meeting</strong>: Saturday 28 Nov 2026, 10:00, sacco hall.</li>
            <li><strong>Loan applications</strong>: close 15 Nov 2026 for the December cycle.</li>
        </ul>
    </section>
<?php
render_bottom();
