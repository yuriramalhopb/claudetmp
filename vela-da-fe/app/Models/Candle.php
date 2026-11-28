<?php
declare(strict_types=1);

namespace App\Models;

use PDOException;

/**
 * Velas. Ciclo de vida:
 *   pending_payment --(Pix confirmado)--> active --(expires_at passa)--> "apagada" (memorial)
 *   free candles já nascem active.   cancelled = desistência/prazo do Pix.   removed = retirada pela moderação.
 * "Acesa agora" = status active E expires_at no futuro (calculado na consulta; não depende de cron).
 */
final class Candle extends Model
{
    protected string $table = 'candles';

    /** chave => [rótulo, emoji] */
    public const CATEGORIES = [
        'saude' => ['Saúde', '💚'],
        'familia' => ['Família', '🏡'],
        'gratidao' => ['Gratidão', '🙏'],
        'falecidos' => ['Pelos falecidos', '🕊️'],
        'paz' => ['Paz', '🌿'],
        'trabalho' => ['Trabalho e finanças', '💼'],
        'estudos' => ['Estudos', '📖'],
        'conversao' => ['Conversão e fé', '✝️'],
        'outros' => ['Outras intenções', '✨'],
    ];

    public const STATUS = [
        'pending_payment' => 'Aguardando pagamento',
        'active' => 'Acesa',
        'cancelled' => 'Cancelada',
        'removed' => 'Removida',
    ];

    public const MOD = ['approved' => 'Aprovada', 'pending' => 'Aguardando moderação', 'rejected' => 'Texto recusado'];

    public const SORTS = ['recent' => 'Mais recentes', 'prayers' => 'Mais orações', 'ending' => 'Perto de apagar'];

    private const PUBLIC_COLUMNS = 'c.id, c.code, c.name, c.show_name, c.for_whom, c.intention, c.show_intention, c.mod_status,
        c.category, c.prayer_count, c.lit_at, c.expires_at, c.status, c.type_id, c.hours, t.name AS type_name, t.color AS type_color';

    // ------------------------------------------------------------ códigos e tokens

    public static function newToken(): string
    {
        return bin2hex(random_bytes(20));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function validCode(string $code): bool
    {
        return (bool) preg_match('/^[a-f0-9]{12}$/', $code);
    }

    public static function price(array $type, array $plan): int
    {
        return (int) $type['price'] + (int) $plan['price'];
    }

    // ------------------------------------------------------------ criação / ciclo de vida

    /**
     * @param array $d campos já validados (ver CandleController)
     * @return array{id:int, code:string}
     */
    public function create(array $d, string $token): array
    {
        $now = self::now();
        $row = [
            'token_hash' => self::hashToken($token),
            'type_id' => (int) $d['type_id'],
            'plan_id' => $d['plan_id'] ?? null,
            'category' => $d['category'],
            'name' => $d['name'],
            'show_name' => !empty($d['show_name']) ? 1 : 0,
            'for_whom' => ($d['for_whom'] ?? '') !== '' ? $d['for_whom'] : null,
            'intention' => ($d['intention'] ?? '') !== '' ? $d['intention'] : null,
            'show_intention' => !empty($d['show_intention']) ? 1 : 0,
            'email' => ($d['email'] ?? '') !== '' ? mb_strtolower($d['email']) : null,
            'notify_email' => !empty($d['notify_email']) ? 1 : 0,
            'mass_intention' => !empty($d['mass_intention']) ? 1 : 0,
            'status' => $d['status'],
            'mod_status' => $d['mod_status'] ?? 'approved',
            'amount' => (int) $d['amount'],
            'hours' => (int) $d['hours'],
            'payment_method' => $d['payment_method'] ?? null,
            'ip_hash' => $d['ip_hash'] ?? null,
            'relit_from' => $d['relit_from'] ?? null,
            'created_at' => $now,
        ];
        if ($row['status'] === 'active') {
            $row['lit_at'] = $now;
            $row['expires_at'] = date('Y-m-d H:i:s', time() + $row['hours'] * 3600);
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $row['code'] = bin2hex(random_bytes(6));
            try {
                return ['id' => $this->db->insert('candles', $row), 'code' => $row['code']];
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') { // só repete se o código colidiu
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Não foi possível gerar um código único para a vela.');
    }

    /** Acende uma vela que aguardava pagamento. */
    public function activate(int $id, string $method = 'pix'): bool
    {
        $c = $this->find($id);
        if ($c === null || $c['status'] !== 'pending_payment') {
            return false;
        }
        $now = time();
        $this->db->update('candles', [
            'status' => 'active',
            'payment_method' => $method,
            'paid_at' => date('Y-m-d H:i:s', $now),
            'lit_at' => date('Y-m-d H:i:s', $now),
            'expires_at' => date('Y-m-d H:i:s', $now + (int) $c['hours'] * 3600),
            'updated_at' => self::now(),
        ], 'id = :id', ['id' => $id]);
        return true;
    }

    /** Prolonga a vela (soma horas ao que resta; se já apagou, conta a partir de agora). */
    public function extend(int $id, int $hours): void
    {
        $c = $this->find($id);
        if ($c === null || $c['status'] !== 'active') {
            return;
        }
        $base = max(time(), (int) strtotime((string) $c['expires_at']));
        $this->db->update('candles', ['expires_at' => date('Y-m-d H:i:s', $base + $hours * 3600), 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    /** Apaga agora (vira memorial). */
    public function extinguish(int $id): void
    {
        $this->db->update('candles', ['expires_at' => self::now(), 'updated_at' => self::now()], "id = :id AND status = 'active'", ['id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->update('candles', ['status' => $status, 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    public function setModStatus(int $id, string $mod): void
    {
        $this->db->update('candles', ['mod_status' => $mod, 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    /** A pessoa avisou que fez o Pix (a equipe confere no banco e confirma). */
    public function claimPaid(int $id): void
    {
        $this->db->update('candles', ['paid_claimed_at' => self::now(), 'updated_at' => self::now()], "id = :id AND status = 'pending_payment'", ['id' => $id]);
    }

    public function setNote(int $id, ?string $note): void
    {
        $this->db->update('candles', ['admin_note' => $note !== '' ? $note : null, 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    public function setMassDone(int $id, bool $done): void
    {
        $this->db->update('candles', ['mass_done_at' => $done ? self::now() : null], 'id = :id', ['id' => $id]);
    }

    /** Gera um novo token (o antigo deixa de valer) — usado ao reenviar o link por e-mail. */
    public function rotateToken(int $id): string
    {
        $token = self::newToken();
        $this->db->update('candles', ['token_hash' => self::hashToken($token)], 'id = :id', ['id' => $id]);
        return $token;
    }

    public function validToken(array $candle, string $token): bool
    {
        return $token !== '' && hash_equals((string) $candle['token_hash'], self::hashToken($token));
    }

    // ------------------------------------------------------------ consultas públicas

    public function findByCode(string $code): ?array
    {
        if (!self::validCode($code)) {
            return null;
        }
        return $this->db->fetch(
            'SELECT c.*, t.name AS type_name, t.color AS type_color, t.description AS type_description
             FROM candles c JOIN candle_types t ON t.id = c.type_id WHERE c.code = ?',
            [$code]
        );
    }

    /**
     * Mural. $scope: 'lit' (acesas agora) ou 'memorial' (apagadas nos últimos 30 dias).
     * @return array{items:array, total:int, pages:int, page:int}
     */
    public function wall(string $scope, array $filters, int $page, int $perPage): array
    {
        $now = self::now();
        $where = ["c.status = 'active'"];
        $params = [];
        if ($scope === 'memorial') {
            $where[] = 'c.expires_at <= :now AND c.expires_at > :since';
            $params['now'] = $now;
            $params['since'] = date('Y-m-d H:i:s', time() - 30 * 86400);
        } else {
            $where[] = 'c.expires_at > :now';
            $params['now'] = $now;
        }
        if (isset(self::CATEGORIES[$filters['category'] ?? ''])) {
            $where[] = 'c.category = :cat';
            $params['cat'] = $filters['category'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'c.type_id = :type';
            $params['type'] = (int) $filters['type'];
        }
        $sql = implode(' AND ', $where);
        $total = (int) $this->db->fetchValue("SELECT COUNT(*) FROM candles c WHERE {$sql}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $order = match ($filters['sort'] ?? 'recent') {
            'prayers' => 'c.prayer_count DESC, c.lit_at DESC',
            'ending' => 'c.expires_at ASC',
            default => $scope === 'memorial' ? 'c.expires_at DESC' : 'c.lit_at DESC',
        };
        $items = $this->db->fetchAll(
            'SELECT ' . self::PUBLIC_COLUMNS . " FROM candles c JOIN candle_types t ON t.id = c.type_id
             WHERE {$sql} ORDER BY {$order}, c.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => ($page - 1) * $perPage]
        );
        return ['items' => array_map([self::class, 'present'], $items), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** Últimas velas acesas (início do site). */
    public function latestLit(int $limit): array
    {
        $rows = $this->db->fetchAll(
            'SELECT ' . self::PUBLIC_COLUMNS . " FROM candles c JOIN candle_types t ON t.id = c.type_id
             WHERE c.status = 'active' AND c.expires_at > ? ORDER BY c.lit_at DESC, c.id DESC LIMIT ?",
            [self::now(), $limit]
        );
        return array_map([self::class, 'present'], $rows);
    }

    /** Contadores públicos do site. */
    public function counters(): array
    {
        $now = self::now();
        return [
            'lit_now' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM candles WHERE status = 'active' AND expires_at > ?", [$now]),
            'lit_total' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM candles WHERE status = 'active'"),
            'lit_today' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM candles WHERE status = 'active' AND lit_at >= ?", [date('Y-m-d 00:00:00')]),
            'prayers' => (int) $this->db->fetchValue("SELECT COALESCE(SUM(prayer_count), 0) FROM candles WHERE status = 'active'"),
        ];
    }

    /** Aplica as regras de privacidade/moderação para exibir uma vela em público. */
    public static function present(array $c, ?int $now = null): array
    {
        $now ??= time();
        $approved = ($c['mod_status'] ?? 'approved') === 'approved';
        $intention = trim((string) ($c['intention'] ?? ''));
        $whom = trim((string) ($c['for_whom'] ?? ''));
        $showText = $approved && !empty($c['show_intention']);
        $c['display_name'] = ($approved && !empty($c['show_name'])) ? $c['name'] : 'Anônimo';
        $c['display_text'] = $showText && $intention !== '' ? $intention : null;
        $c['display_whom'] = $showText && $whom !== '' ? $whom : null;
        $c['text_pending'] = !$approved && ($c['mod_status'] ?? '') === 'pending' && ($intention !== '' || $whom !== '') && !empty($c['show_intention']);
        $c['lit'] = ($c['status'] ?? '') === 'active' && $c['expires_at'] !== null && strtotime((string) $c['expires_at']) > $now;
        $cat = self::CATEGORIES[$c['category'] ?? 'outros'] ?? self::CATEGORIES['outros'];
        $c['category_label'] = $cat[0];
        $c['category_emoji'] = $cat[1];
        return $c;
    }

    // ------------------------------------------------------------ orações pela vela

    /** Registra "rezei por esta intenção" (uma vez por visitante, por vela, por dia). */
    public function pray(int $candleId, string $visitorHash): bool
    {
        $day = date('Y-m-d');
        if ($this->db->fetchValue('SELECT 1 FROM candle_prayers WHERE candle_id = ? AND visitor_hash = ? AND day = ?', [$candleId, $visitorHash, $day])) {
            return false;
        }
        try {
            $this->db->transaction(function () use ($candleId, $visitorHash, $day) {
                $this->db->insert('candle_prayers', ['candle_id' => $candleId, 'visitor_hash' => $visitorHash, 'day' => $day, 'created_at' => self::now()]);
                $this->db->execute('UPDATE candles SET prayer_count = prayer_count + 1 WHERE id = ?', [$candleId]);
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
        return true;
    }

    public function purgeOldPrayers(): void
    {
        $this->db->execute('DELETE FROM candle_prayers WHERE day < ?', [date('Y-m-d', time() - 3 * 86400)]);
    }

    // ------------------------------------------------------------ limites e recuperação

    public function countRecentByIp(string $ipHash, string $since): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM candles WHERE ip_hash = ? AND created_at >= ?', [$ipHash, $since]);
    }

    /** Velas recentes de um e-mail (para reenviar os links de gerenciamento). */
    public function recentByEmail(string $email, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, t.name AS type_name FROM candles c JOIN candle_types t ON t.id = c.type_id
             WHERE c.email = ? AND c.status IN ('active', 'pending_payment') AND c.created_at >= ?
             ORDER BY c.created_at DESC LIMIT ?",
            [mb_strtolower($email), date('Y-m-d H:i:s', time() - 90 * 86400), $limit]
        );
    }

    // ------------------------------------------------------------ painel

    /** @return array{items:array, total:int, pages:int, page:int} */
    public function adminList(array $f, int $page, int $perPage): array
    {
        $now = self::now();
        $where = ['1=1'];
        $params = [];
        switch ($f['status'] ?? '') {
            case 'lit':
                $where[] = "c.status = 'active' AND c.expires_at > :now";
                $params['now'] = $now;
                break;
            case 'expired':
                $where[] = "c.status = 'active' AND c.expires_at <= :now";
                $params['now'] = $now;
                break;
            case 'pending_payment':
            case 'cancelled':
            case 'removed':
                $where[] = 'c.status = :status';
                $params['status'] = $f['status'];
                break;
            case 'moderation':
                $where[] = "c.mod_status = 'pending' AND c.status IN ('active', 'pending_payment')";
                break;
        }
        if (!empty($f['type'])) {
            $where[] = 'c.type_id = :type';
            $params['type'] = (int) $f['type'];
        }
        if (isset(self::CATEGORIES[$f['category'] ?? ''])) {
            $where[] = 'c.category = :cat';
            $params['cat'] = $f['category'];
        }
        if (!empty($f['mass'])) {
            $where[] = 'c.mass_intention = 1';
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            // MySQL (prepares nativos) não aceita repetir o mesmo parâmetro nomeado: um nome por coluna
            $cols = ['name', 'email', 'code', 'for_whom', 'intention'];
            $where[] = '(' . implode(' OR ', array_map(static fn ($c) => "c.{$c} LIKE :q_{$c} ESCAPE '!'", $cols)) . ')';
            foreach ($cols as $col) {
                $params['q_' . $col] = self::like($q);
            }
        }
        $sql = implode(' AND ', $where);
        $total = (int) $this->db->fetchValue("SELECT COUNT(*) FROM candles c WHERE {$sql}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = $this->db->fetchAll(
            "SELECT c.*, t.name AS type_name, t.color AS type_color FROM candles c JOIN candle_types t ON t.id = c.type_id
             WHERE {$sql} ORDER BY c.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** Todas as velas (exportação CSV), em lotes para não estourar memória. */
    public function exportRows(string $from, string $to): \Generator
    {
        $lastId = PHP_INT_MAX;
        while (true) {
            $rows = $this->db->fetchAll(
                "SELECT c.*, t.name AS type_name FROM candles c JOIN candle_types t ON t.id = c.type_id
                 WHERE c.id < :last AND c.created_at >= :from AND c.created_at < :to ORDER BY c.id DESC LIMIT 500",
                ['last' => $lastId, 'from' => $from . ' 00:00:00', 'to' => date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00']
            );
            if ($rows === []) {
                return;
            }
            foreach ($rows as $row) {
                yield $row;
                $lastId = (int) $row['id'];
            }
        }
    }

    /** Intenções para a missa: velas já acesas (pagas, se for o caso) marcadas para serem lembradas. */
    public function massIntentions(string $from, string $to, bool $onlyPending): array
    {
        $sql = "SELECT c.*, t.name AS type_name FROM candles c JOIN candle_types t ON t.id = c.type_id
                WHERE c.mass_intention = 1 AND c.status = 'active' AND c.mod_status <> 'rejected'
                  AND c.lit_at >= :from AND c.lit_at < :to";
        if ($onlyPending) {
            $sql .= ' AND c.mass_done_at IS NULL';
        }
        return $this->db->fetchAll(
            $sql . ' ORDER BY c.lit_at ASC, c.id ASC',
            ['from' => $from . ' 00:00:00', 'to' => date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00']
        );
    }

    // ------------------------------------------------------------ rotinas automáticas

    /** Velas com e-mail que apagam em menos de 24 h (só as de 2+ dias) e ainda não foram avisadas. */
    public function dueReminders(): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, t.name AS type_name FROM candles c JOIN candle_types t ON t.id = c.type_id
             WHERE c.status = 'active' AND c.notify_email = 1 AND c.email IS NOT NULL AND c.reminder_sent_at IS NULL
               AND c.hours >= 48 AND c.expires_at > :now AND c.expires_at <= :soon LIMIT 100",
            ['now' => self::now(), 'soon' => date('Y-m-d H:i:s', time() + 86400)]
        );
    }

    /** Velas que apagaram nos últimos 3 dias e cujo dono ainda não foi avisado. */
    public function dueExpiredNotices(): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, t.name AS type_name FROM candles c JOIN candle_types t ON t.id = c.type_id
             WHERE c.status = 'active' AND c.notify_email = 1 AND c.email IS NOT NULL AND c.expired_notice_at IS NULL
               AND c.expires_at <= :now AND c.expires_at > :since LIMIT 100",
            ['now' => self::now(), 'since' => date('Y-m-d H:i:s', time() - 3 * 86400)]
        );
    }

    public function markReminderSent(int $id): void
    {
        $this->db->update('candles', ['reminder_sent_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    public function markExpiredNoticeSent(int $id): void
    {
        $this->db->update('candles', ['expired_notice_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    /** Cancela pedidos de vela paga que ficaram sem pagamento. */
    public function cancelStalePending(int $hours = 72): int
    {
        return $this->db->execute(
            "UPDATE candles SET status = 'cancelled', updated_at = ? WHERE status = 'pending_payment' AND created_at < ?",
            [self::now(), date('Y-m-d H:i:s', time() - $hours * 3600)]
        );
    }

    /** LGPD: apaga e-mail e IP de velas que já apagaram há mais de $days dias. */
    public function anonymizeOld(int $days = 180): int
    {
        return $this->db->execute(
            "UPDATE candles SET email = NULL, ip_hash = NULL, notify_email = 0
             WHERE (email IS NOT NULL OR ip_hash IS NOT NULL) AND expires_at IS NOT NULL AND expires_at < ?",
            [date('Y-m-d H:i:s', time() - $days * 86400)]
        );
    }
}
