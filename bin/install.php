<?php
declare(strict_types=1);

/**
 * Instalação: cria as tabelas e o primeiro administrador.
 * Uso: php bin/install.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}

$config = require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Validator;
use App\Models\User;

$db = new Database($config->get('db'));
$applied = $db->migrate(BASE_PATH . '/database');
echo "✔ Tabelas criadas/atualizadas." . ($applied ? ' Migrações: ' . implode(', ', $applied) : '') . "\n";

$users = new User($db);
if ($users->countAdmins() > 0) {
    echo "✔ Já existe administrador. Nada mais a fazer.\n";
    exit(0);
}

$ask = static function (string $label, bool $hidden = false): string {
    echo $label;
    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
        $v = trim((string) fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $v;
    }
    return trim((string) fgets(STDIN));
};

echo "\nCriar o primeiro administrador:\n";
$name = $ask('Nome: ');
$email = $ask('E-mail: ');
$password = $ask('Senha (8+ caracteres, letras e números): ', true);

$v = Validator::make(compact('name', 'email', 'password'), [
    'name' => 'required|min:3|max:120',
    'email' => 'required|email',
    'password' => 'required|password',
]);
if ($v->fails()) {
    fwrite(STDERR, '✘ ' . $v->firstError() . "\n");
    exit(1);
}
if ($users->emailExists($email)) {
    fwrite(STDERR, "✘ E-mail já cadastrado.\n");
    exit(1);
}
$users->create(['name' => $name, 'email' => $email], $password, 'approved', 'admin');
echo "✔ Administrador criado! Acesse /entrar e depois /admin.\n";
