<?php
declare(strict_types=1);

/**
 * Prepara o sistema pela linha de comando (opcional: o site também se instala sozinho na primeira visita):
 *   php bin/install.php                       -> cria as tabelas e o conteúdo inicial
 *   php bin/install.php admin@igreja.com.br   -> ...e cria o primeiro administrador (pede a senha)
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';
$app = new App\Core\App($config, BASE_PATH); // migra o banco e cria o conteúdo inicial
echo "Banco pronto (" . $app->db->driver() . ").\n";

$email = $argv[1] ?? '';
if ($email !== '') {
    $users = $app->model(App\Models\User::class);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "E-mail inválido.\n");
        exit(1);
    }
    if ($users->emailExists($email)) {
        fwrite(STDERR, "Já existe um usuário com esse e-mail. Para trocar a senha: php bin/reset-password.php {$email}\n");
        exit(1);
    }
    $password = prompt_password();
    $id = $users->create('Administrador', $email, $password, 'admin');
    echo "Administrador #{$id} criado: {$email}\n";
}
echo "Pronto! Abra o site e entre em /admin.\n";

function prompt_password(): string
{
    for (;;) {
        echo 'Senha (8+ caracteres, com letras e números): ';
        if (DIRECTORY_SEPARATOR !== '\\') {
            system('stty -echo 2>/dev/null');
        }
        $p1 = trim((string) fgets(STDIN));
        echo "\nRepita a senha: ";
        $p2 = trim((string) fgets(STDIN));
        if (DIRECTORY_SEPARATOR !== '\\') {
            system('stty echo 2>/dev/null');
        }
        echo "\n";
        $v = App\Core\Validator::make(['password' => $p1, 'password_confirmation' => $p2], ['password' => 'required|password|confirmed']);
        if (!$v->fails()) {
            return $p1;
        }
        echo $v->firstError() . "\n";
    }
}
