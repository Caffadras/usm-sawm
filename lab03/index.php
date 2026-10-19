<?php
declare(strict_types=1);

function openDatabase(): PDO
{
    $database = new PDO('sqlite:' . __DIR__ . '/guest.sqlite');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $database->exec(
        'CREATE TABLE IF NOT EXISTS guest (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user TEXT NOT NULL,
            text_message TEXT NOT NULL,
            e_mail TEXT NOT NULL,
            data_time_message TEXT NOT NULL
        )'
    );

    return $database;
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$database = openDatabase();
$mode = $_POST['mode'] ?? 'vulnerable';
$error = null;
$saved = false;

// Same storage path for both modes, so one set of rows can be shown either way.
// Prepared statement keeps the raw input out of the SQL command.
function saveEntry(PDO $database, string $user, string $email, string $message): void
{
    $statement = $database->prepare(
        'INSERT INTO guest (user, text_message, e_mail, data_time_message)
         VALUES (:user, :text_message, :e_mail, :data_time_message)'
    );
    $statement->execute([
        ':user' => $user,
        ':text_message' => $message,
        ':e_mail' => $email,
        ':data_time_message' => date('Y-m-d H:i:s'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['clear'])) {
            $database->exec('DELETE FROM guest');
        } elseif (!isset($_POST['switch'])) {
            $user = trim((string) ($_POST['user'] ?? ''));
            $email = trim((string) ($_POST['e_mail'] ?? ''));
            $message = trim((string) ($_POST['text_message'] ?? ''));

            // Both modes store the same raw input. The XSS defense is output
            // escaping, applied only in safe mode when the rows are printed.
            saveEntry($database, $user, $email, $message);
            $saved = true;
        }
    } catch (PDOException $exception) {
        $error = $exception->getMessage();
    }
}

$rows = $database
    ->query('SELECT id, user, text_message, e_mail, data_time_message FROM guest ORDER BY id DESC')
    ->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>XSS lab</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { max-width: 960px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        form { border: 1px solid #8888; border-radius: .5rem; padding: 1rem; }
        label, input, textarea, button { display: block; }
        input, textarea { box-sizing: border-box; width: 100%; margin: .4rem 0 1rem; padding: .55rem; }
        button { padding: .5rem .8rem; }
        .mode-toggle { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .mode-toggle a { padding: .5rem .8rem; border: 1px solid #8888; border-radius: .5rem; text-decoration: none; }
        .mode-toggle a.active { background: #8884; font-weight: bold; }
        .banner { border-radius: .5rem; padding: .6rem 1rem; margin: 1rem 0; }
        .banner.vulnerable { background: #c6282822; border: 1px solid #c62828; }
        .banner.safe { background: #2e7d3222; border: 1px solid #2e7d32; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #8888; padding: .45rem; text-align: left; vertical-align: top; }
        .warning { color: #c62828; }
        .error { color: #c62828; font-weight: bold; }
        .ok { color: #2e7d32; }
        .clear { display: inline; border: none; padding: 0; margin: 0; }
    </style>
</head>
<body>
    <h1>XSS lab: guest-book</h1>

    <div class="mode-toggle">
        <form method="post" class="clear">
            <input type="hidden" name="mode" value="vulnerable">
            <button type="submit" name="switch">Vulnerable mode</button>
        </form>
        <form method="post" class="clear">
            <input type="hidden" name="mode" value="safe">
            <button type="submit" name="switch">Safe mode</button>
        </form>
    </div>

    <?php if ($mode === 'vulnerable'): ?>
        <div class="banner vulnerable">
            <strong class="warning">Vulnerable mode.</strong>
            Stored messages are printed without escaping. Injected HTML and
            JavaScript run when this page loads. No input checks.
        </div>
    <?php else: ?>
        <div class="banner safe">
            <strong class="ok">Safe mode.</strong>
            Every field is escaped on output with htmlspecialchars().
            Injected code shows as plain text. It does not run.
        </div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="error"><?= escape($error) ?></p>
    <?php elseif ($saved): ?>
        <p class="ok">Entry saved.</p>
    <?php endif; ?>

    <form method="post">
        <h2>Sign the guest-book</h2>
        <input type="hidden" name="mode" value="<?= escape($mode) ?>">

        <label for="user">Name</label>
        <input id="user" name="user" required>

        <label for="e_mail">E-mail</label>
        <input id="e_mail" name="e_mail" required>

        <label for="text_message">Message</label>
        <textarea id="text_message" name="text_message" rows="3" required></textarea>

        <button type="submit">Add entry</button>
    </form>

    <h2>Entries (<?= count($rows) ?>)</h2>

    <?php if ($rows !== []): ?>
        <form method="post" class="clear">
            <input type="hidden" name="mode" value="<?= escape($mode) ?>">
            <button type="submit" name="clear" value="1">Clear all entries</button>
        </form>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <p>No entries yet.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Message</th><th>E-mail</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= escape((string) $row['id']) ?></td>
                    <?php if ($mode === 'vulnerable'): ?>
                        <td><?= $row['user'] ?></td>
                        <td><?= $row['text_message'] ?></td>
                    <?php else: ?>
                        <td><?= escape((string) $row['user']) ?></td>
                        <td><?= escape((string) $row['text_message']) ?></td>
                    <?php endif; ?>
                    <td><?= escape((string) $row['e_mail']) ?></td>
                    <td><?= escape((string) $row['data_time_message']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
