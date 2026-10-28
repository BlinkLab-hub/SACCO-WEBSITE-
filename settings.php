<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
render_top('Settings', 'settings', $user);
$theme = $user['theme'] === 'dark' ? 'dark' : 'light';
?>
<?= page_header('Settings', 'Manage your profile, appearance, password and notifications.') ?>

<section class="grid-2">
    <article class="panel">
        <h2>Profile</h2>
        <form class="form" method="post" action="action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="back" value="settings.php">
            <label class="field">Full name
                <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required>
            </label>
            <label class="field">Email
                <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" placeholder="you@example.com">
            </label>
            <div class="form-actions">
                <button class="btn primary" type="submit">Save profile</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <h2>Appearance</h2>
        <form class="form" method="post" action="action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_theme">
            <input type="hidden" name="back" value="settings.php">
            <fieldset class="field">
                <legend>Theme</legend>
                <div class="seg">
                    <label class="seg-opt">
                        <input type="radio" name="theme" value="light"<?= $theme === 'light' ? ' checked' : '' ?>>
                        <span>Light mode</span>
                    </label>
                    <label class="seg-opt">
                        <input type="radio" name="theme" value="dark"<?= $theme === 'dark' ? ' checked' : '' ?>>
                        <span>Dark mode</span>
                    </label>
                </div>
            </fieldset>
            <div class="form-actions">
                <button class="btn primary" type="submit">Save appearance</button>
            </div>
        </form>
    </article>
</section>

<section class="grid-2">
    <article class="panel">
        <h2>Change password</h2>
        <form class="form" method="post" action="action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="back" value="settings.php">
            <label class="field">Current password
                <input type="password" name="current" autocomplete="current-password" required>
            </label>
            <label class="field">New password (8+ characters)
                <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
            </label>
            <label class="field">Confirm new password
                <input type="password" name="confirm" autocomplete="new-password" required>
            </label>
            <div class="form-actions">
                <button class="btn primary" type="submit">Update password</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <h2>Notifications</h2>
        <form class="checks" method="post" action="action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_notifications">
            <input type="hidden" name="back" value="settings.php">
            <label><input type="checkbox" name="notify_sms" value="1"<?= (int)$user['notify_sms'] ? ' checked' : '' ?>> SMS alerts</label>
            <label><input type="checkbox" name="notify_email" value="1"<?= (int)$user['notify_email'] ? ' checked' : '' ?>> Email newsletters and announcements</label>
            <button class="btn primary" type="submit">Save notifications</button>
        </form>
    </article>
</section>
<?php
render_bottom();
