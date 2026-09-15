<?php
declare(strict_types=1);

function createDatabase(): PDO
{
    $database = new PDO('sqlite::memory:');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $database->exec(
        'CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            username TEXT NOT NULL,
            role TEXT NOT NULL
        )'
    );

    $insert = $database->prepare(
        'INSERT INTO users (id, username, role) VALUES (:id, :username, :role)'
    );

    $records = [
        [1, 'alice', 'student'],
        [2, 'bob', 'student'],
        [3, 'carol', 'teacher'],
        [4, 'admin', 'administrator'],
    ];

    foreach ($records as [$id, $username, $role]) {
        $insert->execute([
            ':id' => $id,
            ':username' => $username,
            ':role' => $role,
        ]);
    }

    return $database;
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$database = createDatabase();
$mode = $_POST['mode'] ?? '';
$username = $_POST['username'] ?? '';
$rows = [];
$executedSql = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($mode === 'vulnerable') {
            // Intentionally unsafe for this local lesson: input becomes SQL code.
            $executedSql = "SELECT id, username, role FROM users WHERE username = '$username'";
            $rows = $database->query($executedSql)->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($mode === 'safe') {
            // The placeholder keeps input separate from the SQL command.
            $executedSql = 'SELECT id, username, role FROM users WHERE username = :username';
            $statement = $database->prepare($executedSql);
            $statement->execute([':username' => $username]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SQL injection lab</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { max-width: 960px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        .forms { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
        form, .result { border: 1px solid #8888; border-radius: .5rem; padding: 1rem; }
        label, input, button { display: block; }
        input { box-sizing: border-box; width: 100%; margin: .4rem 0 1rem; padding: .55rem; }
        button { padding: .5rem .8rem; }
        code { overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #8888; padding: .45rem; text-align: left; }
        .warning, .error { color: #c62828; }
    </style>
</head>
<body>
    <h1>SQL injection lab</h1>
    <p>This page creates a fresh in-memory database on every request.</p>

    <div class="forms">
        <form method="post">
            <h2 class="warning">Vulnerable search</h2>
            <p>The page joins your input directly into the SQL text.</p>
            <input type="hidden" name="mode" value="vulnerable">
            <label for="vulnerable-username">Username</label>
            <input id="vulnerable-username" name="username" value="<?= $mode === 'vulnerable' ? escape($username) : '' ?>" required>
            <button type="submit">Search vulnerable form</button>
        </form>

        <form method="post">
            <h2>Safe search</h2>
            <p>The page sends your input through a prepared statement.</p>
            <input type="hidden" name="mode" value="safe">
            <label for="safe-username">Username</label>
            <input id="safe-username" name="username" value="<?= $mode === 'safe' ? escape($username) : '' ?>" required>
            <button type="submit">Search safe form</button>
        </form>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <section class="result">
            <h2>Result</h2>
            <p><strong>Mode:</strong> <?= escape($mode) ?></p>
            <p><strong>SQL:</strong> <code><?= escape((string) $executedSql) ?></code></p>

            <?php if ($error !== null): ?>
                <p class="error"><strong>Database error:</strong> <?= escape($error) ?></p>
            <?php elseif ($rows === []): ?>
                <p>No matching records.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>Username</th><th>Role</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= escape((string) $row['id']) ?></td>
                            <td><?= escape((string) $row['username']) ?></td>
                            <td><?= escape((string) $row['role']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</body>
</html>
