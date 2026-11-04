<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;

final class User extends Model
{
    protected string $table = 'users';

    public const EXPERIENCE = ['iniciante' => 'Iniciante', 'intermediario' => 'Intermediário', 'avancado' => 'Avançado', 'expert' => 'Expert'];
    public const BLOOD = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    public const STATUS = ['pending' => 'Aguardando aprovação', 'approved' => 'Aprovado', 'blocked' => 'Bloqueado'];
    public const ROLES = ['member' => 'Trilheiro', 'admin' => 'Administrador'];

    /** Campos que o próprio usuário pode editar (whitelist contra mass assignment). */
    public const PROFILE_FIELDS = [
        'name', 'nickname', 'phone', 'city', 'birth_date', 'bike_brand', 'bike_model', 'bike_cc',
        'bike_year', 'experience', 'blood_type', 'emergency_name', 'emergency_phone', 'instagram', 'bio',
    ];

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        return (bool) $this->db->fetchValue(
            'SELECT 1 FROM users WHERE email = ? AND id <> ?',
            [mb_strtolower(trim($email)), $ignoreId ?? 0]
        );
    }

    public function create(array $data, string $password, string $status = 'pending', string $role = 'member'): int
    {
        $row = self::nullify(array_intersect_key($data, array_flip(self::PROFILE_FIELDS)));
        $row = self::castNumbers($row);
        $row['instagram'] = isset($row['instagram']) ? ltrim((string) $row['instagram'], '@') : null;
        $row['email'] = mb_strtolower(trim($data['email']));
        $row['password_hash'] = Auth::hashPassword($password);
        $row['show_phone'] = !empty($data['show_phone']) ? 1 : 0;
        $row['status'] = $status;
        $row['role'] = $role;
        $row['session_version'] = 1;
        $row['created_at'] = self::now();
        return $this->db->insert('users', $row);
    }

    public function updateProfile(int $id, array $data): void
    {
        $row = self::nullify(array_intersect_key($data, array_flip(self::PROFILE_FIELDS)));
        $row = self::castNumbers($row);
        if (array_key_exists('instagram', $row) && $row['instagram'] !== null) {
            $row['instagram'] = ltrim((string) $row['instagram'], '@');
        }
        if (array_key_exists('show_phone', $data)) {
            $row['show_phone'] = $data['show_phone'] ? 1 : 0;
        }
        $row['updated_at'] = self::now();
        $this->db->update('users', $row, 'id = :id', ['id' => $id]);
    }

    public function updatePhoto(int $id, ?string $photo): void
    {
        $this->db->update('users', ['photo' => $photo, 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    /** Troca a senha; por padrão invalida todas as sessões abertas do usuário. */
    public function updatePassword(int $id, string $password, bool $invalidateSessions = true): void
    {
        $this->db->update('users', ['password_hash' => Auth::hashPassword($password), 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
        if ($invalidateSessions) {
            $this->bumpSessionVersion($id);
        }
    }

    public function bumpSessionVersion(int $id): void
    {
        $this->db->execute('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->update('users', ['last_login_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!isset(self::STATUS[$status])) {
            return;
        }
        $this->db->update('users', ['status' => $status, 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
        if ($status === 'blocked') {
            $this->bumpSessionVersion($id);
        }
    }

    public function setRole(int $id, string $role): void
    {
        if (isset(self::ROLES[$role])) {
            $this->db->update('users', ['role' => $role, 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
        }
    }

    public function countAdmins(): int
    {
        return $this->count("role = 'admin' AND status = 'approved'");
    }

    /** Diretório público de trilheiros (apenas aprovados). */
    public function directory(string $search, string $experience, int $limit, int $offset): array
    {
        [$where, $params] = $this->directoryWhere($search, $experience);
        return $this->db->fetchAll(
            "SELECT id, name, nickname, city, bike_brand, bike_model, bike_cc, experience, instagram, photo, created_at
             FROM users WHERE {$where} ORDER BY COALESCE(nickname, name) LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
    }

    public function directoryCount(string $search, string $experience): int
    {
        [$where, $params] = $this->directoryWhere($search, $experience);
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM users WHERE {$where}", $params);
    }

    private function directoryWhere(string $search, string $experience): array
    {
        $where = "status = 'approved'";
        $params = [];
        if ($search !== '') {
            $where .= " AND (name LIKE ? ESCAPE '!' OR nickname LIKE ? ESCAPE '!' OR city LIKE ? ESCAPE '!' OR bike_model LIKE ? ESCAPE '!' OR bike_brand LIKE ? ESCAPE '!')";
            $like = self::like($search);
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (isset(self::EXPERIENCE[$experience])) {
            $where .= ' AND experience = ?';
            $params[] = $experience;
        }
        return [$where, $params];
    }

    /** Listagem administrativa. */
    public function adminList(string $search, string $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->adminWhere($search, $status);
        return $this->db->fetchAll(
            "SELECT * FROM users WHERE {$where} ORDER BY CASE WHEN status = 'pending' THEN 0 ELSE 1 END, created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
    }

    public function adminCount(string $search, string $status): int
    {
        [$where, $params] = $this->adminWhere($search, $status);
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM users WHERE {$where}", $params);
    }

    private function adminWhere(string $search, string $status): array
    {
        $where = '1=1';
        $params = [];
        if ($search !== '') {
            $where .= " AND (name LIKE ? ESCAPE '!' OR nickname LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR city LIKE ? ESCAPE '!')";
            $like = self::like($search);
            array_push($params, $like, $like, $like, $like);
        }
        if (isset(self::STATUS[$status])) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }
        return [$where, $params];
    }

    public function allForExport(): array
    {
        return $this->db->fetchAll('SELECT * FROM users ORDER BY name');
    }

    public function latestApproved(int $limit): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, nickname, photo, bike_model, city FROM users WHERE status = 'approved' ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }

    private static function castNumbers(array $row): array
    {
        foreach (['bike_cc', 'bike_year'] as $k) {
            if (array_key_exists($k, $row)) {
                $row[$k] = $row[$k] === null ? null : (int) $row[$k];
            }
        }
        return $row;
    }
}
