<?php
declare(strict_types=1);

/** Redefine a senha de alguém da equipe: php bin/reset-password.php email@igreja.com.br */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';
$email = $argv[1] ?? '';
$app = new App\Core\App($config, BASE_PATH);
$users = $app->model(App\Models\User::class);
$user = $users->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "Uso: php bin/reset-password.php email@igreja.com.br (usuário não encontrado)\n");
    exit(1);
}
for (;;) {
    echo 'Nova senha (8+ caracteres, com letras e números): ';
    $p1 = trim((string) fgets(STDIN));
    echo 'Repita a senha: ';
    $p2 = trim((string) fgets(STDIN));
    $v = App\Core\Validator::make(['password' => $p1, 'password_confirmation' => $p2], ['password' => 'required|password|confirmed']);
    if (!$v->fails()) {
        break;
    }
    echo $v->firstError() . "\n";
}
$users->updatePassword((int) $user['id'], $p1);
$users->setStatus((int) $user['id'], 'active');
echo "Senha de {$user['name']} atualizada e acesso ativado.\n";
