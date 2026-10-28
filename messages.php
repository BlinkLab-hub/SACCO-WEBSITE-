<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$db = db();
$userId = (int)$user['id'];
[$vis, $visParams] = visibility($user, 'm');

// Opening a message marks it as read, which lowers the unread badge.
$activeId = (int)($_GET['id'] ?? 0);
$active = null;
if ($activeId > 0) {
    $active = fetch_one("SELECT m.* FROM messages m WHERE m.id = :id AND {$vis}", [':id' => $activeId] + $visParams);
    if ($active && !fetch_one('SELECT 1 AS x FROM message_reads WHERE user_id = ? AND message_id = ?', [$userId, $activeId])) {
        $db->prepare('INSERT INTO message_reads (user_id, message_id, read_at) VALUES (?,?,?)')
            ->execute([$userId, $activeId, now()]);
    }
}

$stmt = $db->prepare(
    "SELECT m.id, m.subject, m.sender_name, m.created_at,
        (SELECT COUNT(*) FROM message_reads r WHERE r.message_id = m.id AND r.user_id = :reader) AS is_read
     FROM messages m WHERE {$vis}
     ORDER BY m.created_at DESC, m.id DESC"
);
$stmt->execute([':reader' => $userId] + $visParams);
$list = $stmt->fetchAll();

$recipients = recipient_options($user);

render_top('Messages', 'messages', $user);
?>
<?= page_header(
    'Messages',
    'Messages from the sacco and between members and staff.',
    '<button class="btn primary" type="button" data-toggle="#compose">New message</button>'
) ?>

<section class="panel hidden" id="compose">
    <h2>New message</h2>
    <form class="reply-form" method="post" action="action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_message">
        <input type="hidden" name="back" value="messages.php">
        <label class="field">To
            <select name="recipient">
                <?php foreach ($recipients as $value => $label): ?>
                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">Subject
            <input type="text" name="subject" maxlength="200" required>
        </label>
        <label class="field">Message
            <textarea name="body" required></textarea>
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Send message</button>
        </div>
    </form>
</section>

<section class="messages">
    <div class="panel thread-list">
        <?php foreach ($list as $row): ?>
            <a class="thread<?= (int)$row['is_read'] === 0 ? ' unread' : '' ?><?= $activeId === (int)$row['id'] ? ' active' : '' ?>"
               href="messages.php?id=<?= (int)$row['id'] ?>">
                <span class="thread-from"><?= e($row['sender_name']) ?></span>
                <span class="thread-subject"><?= e($row['subject']) ?></span>
                <span class="muted small"><?= e(fmt_date(substr($row['created_at'], 0, 10))) ?></span>
            </a>
        <?php endforeach; ?>
        <?php if (!$list): ?>
            <p class="muted empty">No messages yet.</p>
        <?php endif; ?>
    </div>

    <div class="panel thread-view">
        <?php if ($active): $isMine = isset($active['from_user_id']) && (int)$active['from_user_id'] === $userId; ?>
            <h2><?= e($active['subject']) ?></h2>
            <p class="muted small">From <?= e($active['sender_name']) ?> · <?= e(fmt_date(substr($active['created_at'], 0, 10))) ?></p>
            <div class="msg"><p><?= nl2br(e($active['body'])) ?></p></div>

            <?php if (!$isMine): ?>
                <form class="reply-form" method="post" action="action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="back" value="messages.php">
                    <input type="hidden" name="recipient" value="<?= isset($active['from_user_id']) ? 'user:' . (int)$active['from_user_id'] : 'staff' ?>">
                    <input type="hidden" name="subject" value="Re: <?= e($active['subject']) ?>">
                    <label class="field">Reply
                        <textarea name="body" placeholder="Write your reply" required></textarea>
                    </label>
                    <div class="form-actions">
                        <button class="btn primary" type="submit">Send reply</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="muted small">You sent this message.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="muted">Select a message to read it.</p>
        <?php endif; ?>
    </div>
</section>
<?php
render_bottom();
