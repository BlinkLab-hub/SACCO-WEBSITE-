<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
require_staff($user);
$db = db();

// CSV downloads: reports.php?export=members|contributions|loans|overdue
if (isset($_GET['export'])) {
    switch ($_GET['export']) {
        case 'members':
            $rows = $db->query(
                "SELECT m.member_no, m.full_name, m.phone, COALESCE(m.email, ''), m.joined, m.status,
                    (SELECT COALESCE(SUM(c.amount),0) FROM contributions c
                        WHERE c.member_id = m.id AND c.type IN ('Savings','Shares')) AS savings
                 FROM members m ORDER BY m.member_no"
            )->fetchAll(PDO::FETCH_NUM);
            csv_download('member-list.csv', ['Member No', 'Name', 'Phone', 'Email', 'Joined', 'Status', 'Savings (UGX)'], $rows);
        case 'contributions':
            $rows = $db->query(
                "SELECT c.paid_on, m.full_name, c.type, c.method, c.ref, c.amount
                 FROM contributions c JOIN members m ON m.id = c.member_id ORDER BY c.paid_on DESC, c.id DESC"
            )->fetchAll(PDO::FETCH_NUM);
            csv_download('contributions.csv', ['Date', 'Member', 'Type', 'Method', 'Reference', 'Amount (UGX)'], $rows);
        case 'loans':
            $rows = $db->query(
                "SELECT l.loan_no, m.full_name, l.amount, l.balance, l.monthly_payment, COALESCE(l.due, ''), l.status
                 FROM loans l JOIN members m ON m.id = l.member_id ORDER BY l.id"
            )->fetchAll(PDO::FETCH_NUM);
            csv_download('loan-register.csv', ['Loan', 'Member', 'Amount (UGX)', 'Balance (UGX)', 'Monthly (UGX)', 'Due', 'Status'], $rows);
        case 'overdue':
            $rows = $db->query(
                "SELECT l.loan_no, m.full_name, l.balance, COALESCE(l.due, '')
                 FROM loans l JOIN members m ON m.id = l.member_id WHERE l.status = 'Overdue' ORDER BY l.due"
            )->fetchAll(PDO::FETCH_NUM);
            csv_download('overdue-report.csv', ['Loan', 'Member', 'Balance (UGX)', 'Due'], $rows);
    }
    error_page(404, 'Report not found', 'That report does not exist.');
}

render_top('Reports', 'reports', $user);

$reports = [
    ['Member list', 'All members with contact details, join dates, savings and status.', 'members'],
    ['Contributions', 'Every savings, shares and welfare payment recorded in the sacco.', 'contributions'],
    ['Loan register', 'Loan amounts, balances, due dates and status for every loan.', 'loans'],
    ['Overdue report', 'Overdue loans that need follow-up.', 'overdue'],
];
?>
<?= page_header('Reports', 'Download reports as spreadsheet (CSV) files.') ?>

<section class="reports-grid">
    <?php foreach ($reports as [$title, $desc, $key]): ?>
        <article class="report">
            <h2><?= e($title) ?></h2>
            <p><?= e($desc) ?></p>
            <a class="btn" href="reports.php?export=<?= e($key) ?>">Download CSV</a>
        </article>
    <?php endforeach; ?>
</section>
<?php
render_bottom();
