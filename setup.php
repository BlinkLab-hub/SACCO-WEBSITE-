<?php
// One-time setup: creates the tables and the sample accounts.
// Run it once (in a browser or with: php setup.php), then DELETE this file.

require __DIR__ . '/includes/bootstrap.php';

$isCli = PHP_SAPI === 'cli';
function say(string $message): void
{
    global $isCli;
    echo $isCli ? $message . PHP_EOL : '<p>' . e($message) . '</p>';
}

if (!$isCli) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>YESG setup</title></head>'
        . '<body style="font-family:Arial,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem">';
}

$db = db();
$alreadySetUp = false;
try {
    $alreadySetUp = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (PDOException $e) {
    $alreadySetUp = false;
}

if ($alreadySetUp) {
    say('YESG is already set up. Delete setup.php from the server.');
} else {
    $mysql = config()['driver'] === 'mysql';
    $id = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $statements = [
        'DROP TABLE IF EXISTS message_reads',
        'DROP TABLE IF EXISTS messages',
        'DROP TABLE IF EXISTS loans',
        'DROP TABLE IF EXISTS contributions',
        'DROP TABLE IF EXISTS users',
        'DROP TABLE IF EXISTS members',
        "CREATE TABLE members (
            id $id,
            member_no VARCHAR(20) NOT NULL UNIQUE,
            full_name VARCHAR(120) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(120) NULL,
            joined VARCHAR(10) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Active'
        )",
        "CREATE TABLE users (
            id $id,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(120) NULL,
            theme VARCHAR(10) NOT NULL DEFAULT 'light',
            notify_sms INT NOT NULL DEFAULT 1,
            notify_email INT NOT NULL DEFAULT 0,
            member_id INT NULL
        )",
        "CREATE TABLE contributions (
            id $id,
            member_id INT NOT NULL,
            paid_on VARCHAR(10) NOT NULL,
            type VARCHAR(20) NOT NULL,
            method VARCHAR(30) NOT NULL,
            ref VARCHAR(30) NOT NULL,
            amount BIGINT NOT NULL
        )",
        "CREATE TABLE loans (
            id $id,
            loan_no VARCHAR(20) NOT NULL UNIQUE,
            member_id INT NOT NULL,
            amount BIGINT NOT NULL,
            balance BIGINT NOT NULL,
            monthly_payment BIGINT NOT NULL DEFAULT 0,
            term_months INT NOT NULL DEFAULT 12,
            purpose VARCHAR(200) NOT NULL,
            issued_on VARCHAR(10) NOT NULL,
            due VARCHAR(10) NULL,
            status VARCHAR(20) NOT NULL
        )",
        "CREATE TABLE messages (
            id $id,
            from_user_id INT NULL,
            sender_name VARCHAR(120) NOT NULL,
            audience VARCHAR(10) NOT NULL,
            to_user_id INT NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            created_at VARCHAR(19) NOT NULL
        )",
        "CREATE TABLE message_reads (
            user_id INT NOT NULL,
            message_id INT NOT NULL,
            read_at VARCHAR(19) NOT NULL,
            PRIMARY KEY (user_id, message_id)
        )",
    ];
    foreach ($statements as $sql) {
        $db->exec($sql);
    }

    $db->beginTransaction();

    $members = [
        [1, 'YE-001', 'Sarah Nakato', '0772 110 230', 'sarah@example.com', '2024-02-10', 'Active'],
        [2, 'YE-002', 'David Okello', '0701 445 980', null, '2024-03-04', 'Active'],
        [3, 'YE-003', 'Grace Namusoke', '0755 882 114', null, '2024-05-19', 'Overdue'],
        [4, 'YE-004', 'Brian Ssemakula', '0789 230 441', null, '2025-01-12', 'Active'],
        [5, 'YE-005', 'Joan Akello', '0712 667 905', null, '2025-06-30', 'Inactive'],
    ];
    $stmt = $db->prepare('INSERT INTO members (id, member_no, full_name, phone, email, joined, status) VALUES (?,?,?,?,?,?,?)');
    foreach ($members as $row) {
        $stmt->execute($row);
    }

    // Staff accounts (admin, secretary, treasurer) all have the same access.
    $users = [
        [1, 'admin', 'admin246', 'admin', 'Administrator', null, null],
        [2, 'secretary', 'secretary246', 'secretary', 'Secretary', null, null],
        [3, 'treasurer', 'treasurer246', 'treasurer', 'Treasurer', null, null],
        [4, 'sarah', 'sarah246', 'member', 'Sarah Nakato', 'sarah@example.com', 1],
    ];
    $stmt = $db->prepare('INSERT INTO users (id, username, password_hash, role, full_name, email, member_id) VALUES (?,?,?,?,?,?,?)');
    foreach ($users as [$uid, $username, $password, $role, $name, $email, $memberId]) {
        $stmt->execute([$uid, $username, password_hash($password, PASSWORD_DEFAULT), $role, $name, $email, $memberId]);
    }

    $contributions = [
        [1, '2024-02-10', 'Savings', 'Opening balance', 'OPEN-001', 3250000],
        [1, '2024-02-10', 'Shares', 'Opening balance', 'OPEN-002', 1200000],
        [1, '2026-09-14', 'Savings', 'Bank', 'BK1002', 100000],
        [1, '2026-09-28', 'Savings', 'MTN MoMo', 'MM7402', 150000],
        [1, '2026-10-18', 'Savings', 'MTN MoMo', 'MM7731', 150000],
        [2, '2024-03-04', 'Savings', 'Opening balance', 'OPEN-003', 3020000],
        [2, '2026-10-15', 'Shares', 'Cash', 'R-0412', 100000],
        [3, '2024-05-19', 'Savings', 'Opening balance', 'OPEN-004', 1710000],
        [3, '2026-10-09', 'Savings', 'Bank', 'BK1093', 50000],
        [4, '2025-01-12', 'Savings', 'Opening balance', 'OPEN-005', 980000],
        [4, '2026-10-12', 'Welfare', 'Airtel Money', 'AM5520', 20000],
        [5, '2025-06-30', 'Savings', 'Opening balance', 'OPEN-006', 460000],
        [5, '2026-10-03', 'Savings', 'Cash', 'R-0398', 80000],
    ];
    $stmt = $db->prepare('INSERT INTO contributions (member_id, paid_on, type, method, ref, amount) VALUES (?,?,?,?,?,?)');
    foreach ($contributions as $row) {
        $stmt->execute($row);
    }

    $loans = [
        ['LN-101', 1, 2000000, 1200000, 150000, 12, 'School fees', '2026-05-05', '2026-11-05', 'Active'],
        ['LN-102', 2, 1500000, 1500000, 125000, 12, 'Business stock', '2026-10-14', null, 'Pending'],
        ['LN-103', 3, 800000, 430000, 80000, 12, 'Medical bills', '2026-02-10', '2026-10-10', 'Overdue'],
        ['LN-104', 4, 600000, 0, 50000, 12, 'Rent', '2025-09-01', '2026-08-30', 'Cleared'],
    ];
    $stmt = $db->prepare('INSERT INTO loans (loan_no, member_id, amount, balance, monthly_payment, term_months, purpose, issued_on, due, status) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($loans as $row) {
        $stmt->execute($row);
    }

    $messages = [
        [3, 'Treasurer', 'all', null, 'October contribution reminder',
            'Dear members, please make your October contributions by 31 October. Thank you.', '2026-10-19 09:00:00'],
        [2, 'Secretary', 'all', null, 'AGM date confirmed',
            'The Annual General Meeting will be held on Saturday 28 November 2026 at 10:00 at the sacco hall.', '2026-10-15 10:00:00'],
        [null, 'Grace Namusoke', 'staff', null, 'Loan repayment query',
            'Hello, could I get a payment schedule for LN-103? I want to clear the arrears this month.', '2026-10-11 14:30:00'],
    ];
    $stmt = $db->prepare('INSERT INTO messages (from_user_id, sender_name, audience, to_user_id, subject, body, created_at) VALUES (?,?,?,?,?,?,?)');
    foreach ($messages as $row) {
        $stmt->execute($row);
    }

    $db->commit();

    say('Setup complete.');
    say('Staff accounts: admin / admin246, secretary / secretary246, treasurer / treasurer246');
    say('Member account (demo): sarah / sarah246');
    say('Now delete setup.php from the server.');
}

if (!$isCli) {
    echo '</body></html>';
}
