# Lab 02: SQL injection

This small local lab shows why SQL values must use prepared statements.

## Requirements

- PHP 8 or newer
- The PHP PDO SQLite extension

## Run it

From the project root, run:

```sh
php -S 127.0.0.1:8000 -t lab02
```

Then open <http://127.0.0.1:8000>.

Use this lab only on your own computer. Stop the server with `Ctrl+C` when you
finish.

## Inputs to try

First enter `alice` in each form. Both forms return Alice's record.

Then enter this value in the vulnerable form:

```text
' OR 1=1 -- 
```

It changes the vulnerable query into this command:

```sql
SELECT id, username, role FROM users WHERE username = '' OR 1=1 -- '
```

`1=1` is true for every row. `-- ` starts an SQL comment, so SQLite ignores
the last quote. The form returns all four records.

You can also try this value:

```text
' UNION SELECT 99, sqlite_version(), 'injected row' -- 
```

It adds a made-up row to the vulnerable result. The username column shows the
SQLite version.

Try the same values in the safe form. They return no records. The prepared
statement treats the whole value as text. It does not treat any part as SQL.

## What the code demonstrates

The vulnerable form builds SQL by joining strings:

```php
$sql = "SELECT id, username, role FROM users WHERE username = '$username'";
```

The safe form uses a placeholder:

```php
$statement = $database->prepare(
    'SELECT id, username, role FROM users WHERE username = :username'
);
$statement->execute([':username' => $username]);
```

The database is held only in memory. The page recreates its one `users` table
and four records for each request. No database file remains after the request.
