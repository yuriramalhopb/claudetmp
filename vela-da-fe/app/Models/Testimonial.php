<?php
declare(strict_types=1);

namespace App\Models;

/** "Graças alcançadas": testemunhos enviados pelos fiéis e aprovados pela equipe. */
final class Testimonial extends Model
{
    protected string $table = 'testimonials';

    public const STATUS = ['pending' => 'Aguardando', 'approved' => 'Publicado', 'rejected' => 'Recusado'];

    public function create(string $name, string $text, string $ipHash): int
    {
        return $this->db->insert('testimonials', ['name' => $name, 'text' => $text, 'status' => 'pending', 'ip_hash' => $ipHash, 'created_at' => self::now()]);
    }

    public function approved(int $limit, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, text, created_at FROM testimonials WHERE status = 'approved' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function countApproved(): int
    {
        return $this->count("status = 'approved'");
    }

    public function listByStatus(string $status): array
    {
        return $this->db->fetchAll('SELECT * FROM testimonials WHERE status = ? ORDER BY created_at DESC, id DESC LIMIT 200', [$status]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->update('testimonials', ['status' => $status], 'id = :id', ['id' => $id]);
    }
}
