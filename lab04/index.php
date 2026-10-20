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

    $database->exec(
        'CREATE TABLE IF NOT EXISTS user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_plain TEXT NOT NULL,
            password_md5 TEXT NOT NULL,
            password_hash TEXT NOT NULL
        )'
    );

    if ((int) $database->query('SELECT COUNT(*) FROM user')->fetchColumn() === 0) {
        $seed = [
            ['alice', '123456'],
            ['bob', 'qwerty'],
            ['carol', 'password'],
            ['dave', '123456'],
            ['erin', 'Kp7#vQ2!mZ9$wL'],
            ['frank', 'tiger-moon-ladder-47'],
        ];
        foreach ($seed as [$username, $password]) {
            addUser($database, $username, $password);
        }
    }

    return $database;
}

// Demo only: a real app stores just password_hash. The other two columns show
// what an attacker gets when the database leaks.
function addUser(PDO $database, string $username, string $password): void
{
    $statement = $database->prepare(
        'INSERT INTO user (username, password_plain, password_md5, password_hash)
         VALUES (:username, :plain, :md5, :hash)'
    );
    $statement->execute([
        ':username' => $username,
        ':plain' => $password,
        ':md5' => md5($password),
        ':hash' => password_hash($password, PASSWORD_ARGON2ID),
    ]);
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$database = openDatabase();
$action = $_POST['action'] ?? '';
$error = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'entry') {
            $statement = $database->prepare(
                'INSERT INTO guest (user, text_message, e_mail, data_time_message)
                 VALUES (:user, :text_message, :e_mail, :data_time_message)'
            );
            $statement->execute([
                ':user' => trim((string) ($_POST['user'] ?? '')),
                ':text_message' => trim((string) ($_POST['text_message'] ?? '')),
                ':e_mail' => trim((string) ($_POST['e_mail'] ?? '')),
                ':data_time_message' => date('Y-m-d H:i:s'),
            ]);
            $notice = 'Запись добавлена.';
        } elseif ($action === 'register') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if ($username === '' || $password === '') {
                $error = 'Введите логин и пароль.';
            } else {
                addUser($database, $username, $password);
                $notice = "Пользователь $username создан.";
            }
        } elseif ($action === 'login') {
            $statement = $database->prepare('SELECT password_hash FROM user WHERE username = :username');
            $statement->execute([':username' => trim((string) ($_POST['username'] ?? ''))]);
            $hash = $statement->fetchColumn();

                if ($hash !== false && password_verify((string) ($_POST['password'] ?? ''), $hash)) {
                    $notice = 'Вход выполнен: password_verify() подтвердил пароль.';
                } else {
                $error = 'Неверный логин или пароль.';
            }
        }
    } catch (PDOException $exception) {
        $error = $exception->getCode() === '23000'
            ? 'Такой логин уже занят.'
            : $exception->getMessage();
    }
}

$users = $database->query('SELECT * FROM user ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$md5Counts = array_count_values(array_column($users, 'password_md5'));
$entries = $database
    ->query('SELECT user, text_message, e_mail, data_time_message FROM guest ORDER BY id DESC')
    ->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Лаба 4: хранение паролей</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        .forms { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
        form, section { border: 1px solid #8888; border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; }
        label, input, textarea, button { display: block; }
        input, textarea { box-sizing: border-box; width: 100%; margin: .4rem 0 1rem; padding: .55rem; }
        button { padding: .5rem .8rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #8888; padding: .45rem; text-align: left; vertical-align: top; }
        td code { overflow-wrap: anywhere; font-size: .85rem; }
        .plain { color: #c62828; font-weight: bold; }
        .same { background: #f9a82533; }
        .error { color: #c62828; font-weight: bold; }
        .ok { color: #2e7d32; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Лаба 4: хранение паролей</h1>
    <p>Гостевая книга из лабы 3 и новая таблица <code>user</code>. Пароль каждого
    пользователя записан тремя способами, чтобы сравнить их в одной строке.</p>

    <?php if ($error !== null): ?>
        <p class="error"><?= escape($error) ?></p>
    <?php elseif ($notice !== null): ?>
        <p class="ok"><?= escape($notice) ?></p>
    <?php endif; ?>

    <section>
        <h2>Таблица user</h2>
        <p>Жёлтым отмечены одинаковые MD5-хэши. У тех же пользователей Argon2id-хэши разные из-за соли.</p>
        <table>
            <thead><tr><th>id</th><th>username</th><th>password_plain</th><th>password_md5</th><th>password_hash (Argon2id)</th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><?= escape((string) $row['id']) ?></td>
                    <td><?= escape($row['username']) ?></td>
                    <td class="plain"><?= escape($row['password_plain']) ?></td>
                    <td class="<?= $md5Counts[$row['password_md5']] > 1 ? 'same' : '' ?>"><code><?= escape($row['password_md5']) ?></code></td>
                    <td><code><?= escape($row['password_hash']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <div class="forms">
        <form method="post">
            <h2>Регистрация</h2>
            <input type="hidden" name="action" value="register">
            <label for="reg-username">Логин</label>
            <input id="reg-username" name="username" required>
            <label for="reg-password">Пароль</label>
            <input id="reg-password" name="password" type="password" required>
            <button type="submit">Создать</button>
        </form>

        <form method="post">
            <h2>Вход</h2>
            <input type="hidden" name="action" value="login">
            <label for="login-username">Логин</label>
            <input id="login-username" name="username" required>
            <label for="login-password">Пароль</label>
            <input id="login-password" name="password" type="password" required>
            <button type="submit">Войти</button>
        </form>
    </div>

    <form method="post">
        <h2>Гостевая книга</h2>
        <input type="hidden" name="action" value="entry">
        <label for="user">Имя</label>
        <input id="user" name="user" required>
        <label for="e_mail">E-mail</label>
        <input id="e_mail" name="e_mail" required>
        <label for="text_message">Сообщение</label>
        <textarea id="text_message" name="text_message" rows="3" required></textarea>
        <button type="submit">Добавить</button>
    </form>

    <?php if ($entries !== []): ?>
        <table>
            <thead><tr><th>Имя</th><th>Сообщение</th><th>E-mail</th><th>Дата</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $row): ?>
                <tr>
                    <td><?= escape($row['user']) ?></td>
                    <td><?= escape($row['text_message']) ?></td>
                    <td><?= escape($row['e_mail']) ?></td>
                    <td><?= escape($row['data_time_message']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
