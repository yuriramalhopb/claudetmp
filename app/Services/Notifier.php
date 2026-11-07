<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MailQueue;

/**
 * Central de avisos da turma. Cada aviso vira:
 *  - e-mails na fila (enviados pelo cron) para quem aceitou receber e-mail
 *  - uma notificação push (enviada pelo cron) para quem ativou no celular
 */
final class Notifier
{
    public function __construct(
        private readonly Database $db,
        private readonly MailQueue $queue,
        private readonly Mailer $mailer,
        private readonly string $siteUrl,
        private readonly string $siteName,
    ) {
    }

    /** E-mail urgente (ex.: recuperar senha): tenta enviar agora; se falhar, vai para a fila. */
    public function sendNow(string $to, string $subject, string $body): void
    {
        try {
            $this->mailer->send($to, $subject, $this->wrap($body, false));
        } catch (\Throwable) {
            $this->queue->push($to, $subject, $this->wrap($body, false));
        }
    }

    public function queueEmail(string $to, string $subject, string $body, bool $withPrefsFooter = true): void
    {
        $this->queue->push($to, $subject, $this->wrap($body, $withPrefsFooter));
    }

    public function userApproved(array $user): void
    {
        $this->queueEmail($user['email'], 'Seu cadastro foi aprovado! 🦆', "Olá, {$user['name']}!\n\n"
            . "Seu cadastro de trilheiro na {$this->siteName} foi aprovado. Agora você já pode confirmar presença nas trilhas e enviar fotos para a galeria.\n\n"
            . "Próximas trilhas: {$this->siteUrl}/agenda\n\nBora pro barro!", false);
        $this->push('Cadastro aprovado! 🦆', 'Bem-vindo à turma. Confira as próximas trilhas.', '/agenda', [(int) $user['id']]);
    }

    public function newEvent(array $event): int
    {
        $when = date_br($event['start_at'], true);
        $where = trim($event['city'] . (!empty($event['state']) ? '/' . $event['state'] : ''));
        $url = $this->siteUrl . '/agenda/' . (int) $event['id'];
        $subject = 'Trilha nova: ' . $event['title'];
        $body = "Tem trilha nova na agenda!\n\n"
            . "{$event['title']}\n📅 {$when}\n📍 {$where}\n"
            . (!empty($event['price']) ? "💰 {$event['price']}\n" : '')
            . "\nDetalhes e confirmação de presença: {$url}";
        $count = 0;
        foreach ($this->recipients('notify_email') as $u) {
            $this->queueEmail($u['email'], $subject, "Olá, " . self::firstName($u['name']) . "!\n\n" . $body);
            $count++;
        }
        $this->push('Trilha nova! 🏍️', $event['title'] . ' — ' . date('d/m', strtotime($event['start_at'])) . ' em ' . $where, '/agenda/' . (int) $event['id']);
        return $count;
    }

    public function eventReminder(array $event, array $attendees): void
    {
        $url = $this->siteUrl . '/agenda/' . (int) $event['id'];
        $hour = date('H\hi', strtotime($event['start_at']));
        $ids = [];
        foreach ($attendees as $u) {
            $ids[] = (int) $u['id'];
            if ((int) $u['notify_email'] === 1) {
                $this->queueEmail($u['email'], 'É amanhã: ' . $event['title'], 'Olá, ' . self::firstName($u['name']) . "!\n\n"
                    . "Lembrete: amanhã tem {$event['title']} às {$hour}.\n"
                    . (!empty($event['location']) ? "Encontro: {$event['location']} — " : '') . $event['city'] . "\n\n"
                    . "Checklist: capacete, botas, joelheiras, luvas, água, ferramentas e câmara reserva. Moto abastecida!\n\n{$url}");
            }
        }
        if ($ids) {
            $this->push('Amanhã tem trilha! 🏍️', $event['title'] . ' às ' . $hour, '/agenda/' . (int) $event['id'], $ids);
        }
    }

    public function paymentConfirmed(array $user, array $event): void
    {
        $this->queueEmail($user['email'], 'Pagamento confirmado: ' . $event['title'], 'Olá, ' . self::firstName($user['name']) . "!\n\n"
            . "Recebemos o seu pagamento da inscrição em {$event['title']}. Até lá!\n\n{$this->siteUrl}/agenda/" . (int) $event['id'], false);
    }

    public function photoTagged(array $user, int $photoId, string $byName): void
    {
        $this->push('Você foi marcado numa foto 📸', $byName . ' marcou você em uma foto da galeria.', '/fotos/' . $photoId, [(int) $user['id']]);
    }

    /** Cria uma notificação push (enviada pelo cron). $userIds = null → todos os inscritos. */
    public function push(string $title, string $body, string $url, ?array $userIds = null): void
    {
        $this->db->insert('notifications', [
            'title' => mb_substr($title, 0, 120),
            'body' => mb_substr($body, 0, 300),
            'url' => str_starts_with($url, '/') ? mb_substr($url, 0, 255) : '/',
            'only_user_ids' => $userIds === null ? null : implode(',', array_map('intval', $userIds)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recipients(string $flag): array
    {
        $col = $flag === 'notify_push' ? 'notify_push' : 'notify_email';
        return $this->db->fetchAll("SELECT id, name, email FROM users WHERE status = 'approved' AND {$col} = 1");
    }

    private function wrap(string $body, bool $prefsFooter): string
    {
        $footer = "\n\n— {$this->siteName}\n{$this->siteUrl}";
        if ($prefsFooter) {
            $footer .= "\n\nVocê recebe estes avisos porque é trilheiro cadastrado. Para parar, desmarque em: {$this->siteUrl}/minha-conta/editar";
        }
        return $body . $footer;
    }

    private static function firstName(string $name): string
    {
        return explode(' ', trim($name))[0] ?: $name;
    }
}
