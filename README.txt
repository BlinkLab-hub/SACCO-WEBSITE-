YESG SACCO WEBSITE - SETUP

1. Requirements: PHP 8.1 or newer with the PDO extension (SQLite or MySQL).

2. Database
   - Local testing: leave config.php on  'driver' => 'sqlite'.
   - Live hosting (e.g. cPanel): create a MySQL/MariaDB database and user,
     then edit config.php:  'driver' => 'mysql'  and fill in the mysql details.

3. Upload all files to your web host (keep the folder structure).

4. Open setup.php once in a browser (or run: php setup.php).
   It creates the tables and sample accounts.

5. DELETE setup.php from the server afterwards.

6. Sign in with:
   Admin:      admin / admin246
   Secretary:  secretary / secretary246
   Treasurer:  treasurer / treasurer246
   Member:     sarah / sarah246   (demo member account)

   Change these passwords before real use (Settings > Change password).
   Staff accounts (admin, secretary, treasurer) have the same access.

Folders:
  CSS/ and JS/       styles and small front-end script
  IMAGES/            logo
  includes/          shared PHP (database, login checks, layout)
  database/          database file (SQLite) is stored here; keep it private
  action.php         handles every form submission
