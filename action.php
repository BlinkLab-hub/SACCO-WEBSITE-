<?php
// Handles every form submission. Each action checks the user's permissions again on the server.
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$back = safe_back((string)($_POST['back'] ?? 'dashboard.php'));

$fail = static function (string $message) use ($back): never {
    flash($message, 'error');
    redirect($back);
};
$done = static function (string $message, string $to = '') use ($back): never {
    flash($message, 'ok');
    redirect($to !== '' ? $to : $back);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    $fail('Your session expired. Please try again.');
}

$db = db();
$staff = is_staff($user);
$action = (string)($_POST['action'] ?? '');

switch ($action) {
    case 'add_member':
        if (!$staff) {
            $fail('Only staff can add members.');
        }
        $name = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $joined = (string)($_POST['joined'] ?? '');
        if ($name === '' || $phone === '' || !valid_date($joined)) {
            $fail('Please fill in name, phone and a valid joined date.');
        }
        if (!preg_match('/^0\d{9}$/', preg_replace('/\s+/', '', $phone))) {
            $fail('Enter a 10-digit phone number starting with 0.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('Enter a valid email address.');
        }
        $next = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM members')->fetchColumn() + 1;
        $number = 'YE-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        $db->prepare('INSERT INTO members (member_no, full_name, phone, email, joined, status) VALUES (?,?,?,?,?,?)')
            ->execute([$number, $name, $phone, $email !== '' ? $email : null, $joined, 'Active']);
        $done("$name was added as $number.", 'members.php');

    case 'record_contribution':
        if (!$staff) {
            $fail('Only staff can record contributions.');
        }
        $memberId = (int)($_POST['member_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $type = (string)($_POST['type'] ?? '');
        $method = (string)($_POST['method'] ?? '');
        $paidOn = (string)($_POST['paid_on'] ?? '');
        $member = fetch_one('SELECT id, full_name FROM members WHERE id = ?', [$memberId]);
        if (!$member) {
            $fail('Choose a valid member.');
        }
        if ($amount < 1000) {
            $fail('Amount must be at least UGX 1,000.');
        }
        if (!in_array($type, ['Savings', 'Shares', 'Welfare'], true)) {
            $fail('Choose a valid type.');
        }
        if (!in_array($method, ['MTN MoMo', 'Airtel Money', 'Bank', 'Cash'], true)) {
            $fail('Choose a valid payment method.');
        }
        if (!valid_date($paidOn)) {
            $fail('Enter a valid date.');
        }
        $ref = 'R-' . random_int(1000, 9999);
        $db->prepare('INSERT INTO contributions (member_id, paid_on, type, method, ref, amount) VALUES (?,?,?,?,?,?)')
            ->execute([$memberId, $paidOn, $type, $method, $ref, $amount]);
        $done('Contribution of ' . ugx($amount) . ' recorded for ' . $member['full_name'] . ' (' . $ref . ').');

    case 'apply_loan':
        // Members always apply for themselves. Staff choose the member.
        $memberId = $staff ? (int)($_POST['member_id'] ?? 0) : (int)($user['member_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $months = (int)($_POST['months'] ?? 0);
        $purpose = trim((string)($_POST['purpose'] ?? ''));
        if (!fetch_one('SELECT id FROM members WHERE id = ?', [$memberId])) {
            $fail('Choose a valid member.');
        }
        if ($amount < 10000) {
            $fail('Loan amount must be at least UGX 10,000.');
        }
        if (!in_array($months, [3, 6, 12, 24], true)) {
            $fail('Choose a repayment period.');
        }
        if ($purpose === '') {
            $fail('Please state the purpose of the loan.');
        }
        $loanNo = 'LN-' . ((int)$db->query('SELECT COALESCE(MAX(id), 0) FROM loans')->fetchColumn() + 101);
        $monthly = (int)ceil($amount / $months);
        $db->prepare(
            "INSERT INTO loans (loan_no, member_id, amount, balance, monthly_payment, term_months, purpose, issued_on, due, status)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([$loanNo, $memberId, $amount, $amount, $monthly, $months, $purpose, today(), null, 'Pending']);
        $done("Loan application $loanNo was submitted for approval.", 'loans.php');

    case 'approve_loan':
        if (!$staff) {
            $fail('Only staff can approve loans.');
        }
        $loan = fetch_one('SELECT * FROM loans WHERE id = ?', [(int)($_POST['loan_id'] ?? 0)]);
        if (!$loan || $loan['status'] !== 'Pending') {
            $fail('That loan is not waiting for approval.');
        }
        $due = date('Y-m-d', strtotime('+' . (int)$loan['term_months'] . ' months'));
        $db->prepare("UPDATE loans SET status = 'Active', issued_on = ?, due = ? WHERE id = ?")
            ->execute([today(), $due, $loan['id']]);
        $done("Loan {$loan['loan_no']} approved. Final repayment due $due.");

    case 'send_message':
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $target = (string)($_POST['recipient'] ?? '');
        if ($subject === '' || $body === '') {
            $fail('Please add a subject and a message.');
        }
        $audience = '';
        $toUser = null;
        if ($target === 'all') {
            if (!$staff) {
                $fail('Only staff can message everyone.');
            }
            $audience = 'all';
        } elseif ($target === 'staff') {
            $audience = 'staff';
        } elseif (preg_match('/^user:(\d+)$/', $target, $match)) {
            $recipient = fetch_one('SELECT id, role FROM users WHERE id = ?', [(int)$match[1]]);
            if (!$recipient) {
                $fail('That person could not be found.');
            }
            if (!$staff && $recipient['role'] === 'member') {
                $fail('Members can only message staff.');
            }
            $audience = 'user';
            $toUser = (int)$recipient['id'];
        } else {
            $fail('Choose who to send the message to.');
        }
        $db->prepare(
            'INSERT INTO messages (from_user_id, sender_name, audience, to_user_id, subject, body, created_at) VALUES (?,?,?,?,?,?,?)'
        )->execute([(int)$user['id'], $user['full_name'], $audience, $toUser, $subject, $body, now()]);
        $newId = (int)$db->lastInsertId();
        // The sender has already read their own message.
        $db->prepare('INSERT INTO message_reads (user_id, message_id, read_at) VALUES (?,?,?)')
            ->execute([(int)$user['id'], $newId, now()]);
        $done('Message sent.', 'messages.php?id=' . $newId);

    case 'update_profile':
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if ($name === '') {
            $fail('Please enter your full name.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('Enter a valid email address.');
        }
        $emailValue = $email !== '' ? $email : null;
        $db->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?')
            ->execute([$name, $emailValue, (int)$user['id']]);
        if (!empty($user['member_id'])) {
            $db->prepare('UPDATE members SET full_name = ?, email = ? WHERE id = ?')
                ->execute([$name, $emailValue, (int)$user['member_id']]);
        }
        $done('Profile saved.');

    case 'save_theme':
        $theme = (string)($_POST['theme'] ?? '');
        if (!in_array($theme, ['light', 'dark'], true)) {
            $fail('Choose light or dark mode.');
        }
        $db->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute([$theme, (int)$user['id']]);
        $done('Appearance saved: ' . $theme . ' mode.');

    case 'change_password':
        $current = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');
        $row = fetch_one('SELECT password_hash FROM users WHERE id = ?', [(int)$user['id']]);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            $fail('Your current password is not correct.');
        }
        if (strlen($new) < 8) {
            $fail('The new password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            $fail('The new passwords do not match.');
        }
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$user['id']]);
        $done('Password updated.');

    case 'save_notifications':
        $sms = isset($_POST['notify_sms']) ? 1 : 0;
        $email = isset($_POST['notify_email']) ? 1 : 0;
        $db->prepare('UPDATE users SET notify_sms = ?, notify_email = ? WHERE id = ?')
            ->execute([$sms, $email, (int)$user['id']]);
        $done('Notification settings saved.');

    default:
        $fail('Unknown action.');
}
