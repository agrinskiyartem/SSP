<?php
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect('index.php');
}

$username = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Введите логин и пароль.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT user_id, username, password_hash, role FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            setcookie('atm_last_role', $user['role'], time() + 300, '/');
            setcookie('atm_last_user', $user['username'], time() + 300, '/');
            redirect('index.php');
        } else {
            $errors[] = 'Неверные учетные данные.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Вход</h1>
    <?php if ($errors): ?>
        <div class="alert error">
            <?= h(implode(' ', $errors)) ?>
        </div>
    <?php endif; ?>
    <form method="post" class="form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>
            Логин
            <input type="text" name="username" required value="<?= h($username ?: ($_COOKIE['atm_last_user'] ?? '')) ?>">
        </label>
        <label>
            Пароль
            <input type="password" name="password" required>
        </label>
        <button class="button" type="submit">Войти</button>
    </form>
    <p class="muted">Подсказка: после входа роль сохраняется в cookie на 5 минут.</p>
</section>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
