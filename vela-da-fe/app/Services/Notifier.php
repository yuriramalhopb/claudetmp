<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\MailQueue;

/**
 * E-mails do sistema (texto simples). Tudo entra na fila; flush() envia.
 * Só envia para quem informou o e-mail ao acender a vela (e, nos lembretes, marcou o aviso).
 */
final class Notifier
{
    public function __construct(
        private readonly MailQueue $queue,
        private readonly Mailer $mailer,
        private readonly Logger $logger,
        private readonly string $baseUrl,
        private readonly string $churchName,
    ) {
    }

    private function link(array $candle, ?string $token = null): string
    {
        $url = $this->baseUrl . '/vela/' . $candle['code'];
        return $token !== null ? $url . '/minha?t=' . $token : $url;
    }

    /** Vela acesa (ou pedido de vela criado, se for paga). */
    public function candleLit(array $candle, string $token): void
    {
        if (empty($candle['email'])) {
            return;
        }
        $manage = $this->link($candle, $token);
        $until = date_br($candle['expires_at'], true);
        $this->queue->enqueue(
            $candle['email'],
            'Sua vela está acesa 🕯️',
            "Paz e bem, {$candle['name']}!\n\n"
            . "Sua vela ({$candle['type_name']}) foi acesa em nosso mural e ficará acesa até {$until}.\n\n"
            . "Acompanhe, compartilhe ou gerencie sua vela aqui (guarde este link, só você o tem):\n{$manage}\n\n"
            . "Rezamos com você.\n{$this->churchName}"
        );
    }

    public function paymentRequested(array $candle, string $token): void
    {
        if (empty($candle['email'])) {
            return;
        }
        $this->queue->enqueue(
            $candle['email'],
            'Falta pouco para acender sua vela',
            "Paz e bem, {$candle['name']}!\n\n"
            . 'Recebemos o seu pedido de vela (' . money((int) $candle['amount']) . "). Assim que o Pix for confirmado pela nossa equipe, ela será acesa.\n\n"
            . "Para ver o QR Code do Pix novamente:\n" . $this->link($candle, $token) . "\n\n{$this->churchName}"
        );
    }

    public function paymentConfirmed(array $candle, string $token): void
    {
        if (empty($candle['email'])) {
            return;
        }
        $until = date_br($candle['expires_at'], true);
        $this->queue->enqueue(
            $candle['email'],
            'Pagamento confirmado: sua vela está acesa 🕯️',
            "Paz e bem, {$candle['name']}!\n\nConfirmamos o seu Pix e sua vela já está acesa, até {$until}.\n"
            . "Deus lhe pague pela generosidade!\n\nVer sua vela: " . $this->link($candle, $token) . "\n\n{$this->churchName}"
        );
    }

    public function reminder(array $candle, string $token): void
    {
        $this->queue->enqueue(
            $candle['email'],
            'Sua vela está perto de se apagar',
            "Paz e bem, {$candle['name']}!\n\nSua vela ({$candle['type_name']}) se apaga em " . remaining_human($candle['expires_at']) . ".\n"
            . "Se quiser mantê-la acesa, acenda novamente com um clique:\n" . $this->baseUrl . '/acender?repetir=' . $candle['code'] . '&t=' . $token
            . "\n\n{$this->churchName}"
        );
    }

    public function expired(array $candle, string $token): void
    {
        $this->queue->enqueue(
            $candle['email'],
            'Sua vela se apagou — acenda outra?',
            "Paz e bem, {$candle['name']}!\n\nSua vela cumpriu o seu tempo e foi apagada. Que a luz de Deus continue com você.\n"
            . "Se quiser acender novamente com a mesma intenção:\n" . $this->baseUrl . '/acender?repetir=' . $candle['code'] . '&t=' . $token
            . "\n\n{$this->churchName}"
        );
    }

    /** Reenvio dos links de gerenciamento ("Minhas velas"). $items = [[candle, token], ...] */
    public function manageLinks(string $email, array $items): void
    {
        $lines = '';
        foreach ($items as [$candle, $token]) {
            $lines .= '• ' . $candle['type_name'] . ' — ' . ($candle['status'] === 'active' ? 'acesa até ' . date('d/m H:i', strtotime((string) $candle['expires_at'])) : 'aguardando pagamento')
                . "\n  " . $this->link($candle, $token) . "\n";
        }
        $this->queue->enqueue($email, 'Suas velas em ' . $this->churchName, "Paz e bem!\n\nSegue o link das suas velas recentes:\n\n{$lines}\nSe você não pediu isso, pode ignorar este e-mail.\n\n{$this->churchName}");
    }

    /** Envia a fila (até $limit mensagens). @return int enviados */
    public function flush(int $limit = 30): int
    {
        $sent = 0;
        foreach ($this->queue->pending($limit) as $mail) {
            try {
                $this->mailer->send($mail['to_email'], $mail['subject'], $mail['body']);
                $this->queue->markSent((int) $mail['id']);
                $sent++;
            } catch (\Throwable $e) {
                $this->queue->markFailed((int) $mail['id'], $e->getMessage());
                $this->logger->error('E-mail falhou: ' . $e->getMessage(), ['to' => $mail['to_email']]);
            }
        }
        return $sent;
    }
}
