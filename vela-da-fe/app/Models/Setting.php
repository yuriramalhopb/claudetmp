<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Configurações editáveis pelo painel (tabela settings). O SCHEMA abaixo descreve cada campo:
 * o formulário de Admin > Configurações e os valores padrão saem daqui.
 */
final class Setting extends Model
{
    protected string $table = 'settings';
    private ?array $cache = null;

    /** @var array<string, array{group:string, label:string, type:string, default:mixed, help?:string, options?:array}> */
    public const SCHEMA = [
        // ---- Igreja ----
        'church_name' => ['group' => 'Igreja', 'label' => 'Nome da igreja / paróquia', 'type' => 'text', 'default' => 'Nossa Paróquia'],
        'church_tagline' => ['group' => 'Igreja', 'label' => 'Frase abaixo do nome', 'type' => 'text', 'default' => 'Uma luz acesa, uma oração em comunhão'],
        'church_address' => ['group' => 'Igreja', 'label' => 'Endereço', 'type' => 'text', 'default' => ''],
        'church_phone' => ['group' => 'Igreja', 'label' => 'Telefone', 'type' => 'text', 'default' => ''],
        'church_whatsapp' => ['group' => 'Igreja', 'label' => 'WhatsApp da secretaria', 'type' => 'text', 'default' => '', 'help' => 'Com DDD. Usado nos botões "Falar com a secretaria".'],
        'church_email' => ['group' => 'Igreja', 'label' => 'E-mail de contato', 'type' => 'email', 'default' => ''],
        'church_instagram' => ['group' => 'Igreja', 'label' => 'Instagram (usuário)', 'type' => 'text', 'default' => ''],
        'church_maps_url' => ['group' => 'Igreja', 'label' => 'Link do Google Maps', 'type' => 'url', 'default' => ''],
        // ---- Textos ----
        'hero_title' => ['group' => 'Textos do site', 'label' => 'Título da página inicial', 'type' => 'text', 'default' => 'Acenda uma vela de fé'],
        'hero_text' => ['group' => 'Textos do site', 'label' => 'Texto da página inicial', 'type' => 'textarea', 'default' => 'Confie a Deus a sua intenção. Sua vela fica acesa no nosso mural virtual e a comunidade reza com você.'],
        'after_light_text' => ['group' => 'Textos do site', 'label' => 'Mensagem após acender a vela', 'type' => 'textarea', 'default' => "Senhor, acolhe esta luz como sinal da nossa fé. Que ela ilumine os caminhos de quem a acendeu e de todos aqueles por quem rezamos. Amém."],
        'footer_text' => ['group' => 'Textos do site', 'label' => 'Texto do rodapé', 'type' => 'textarea', 'default' => 'Que a luz de Cristo ilumine a sua vida.'],
        'privacy_text' => ['group' => 'Textos do site', 'label' => 'Política de privacidade (opcional)', 'type' => 'textarea', 'default' => '', 'help' => 'Deixe em branco para usar o texto padrão (LGPD).'],
        // ---- Velas ----
        'moderation' => ['group' => 'Velas e moderação', 'label' => 'Moderação dos textos', 'type' => 'select', 'default' => 'auto', 'options' => ['auto' => 'Automática: publica na hora e só segura o que parecer suspeito', 'manual' => 'Manual: toda intenção/nome só aparece após aprovação']],
        'banned_words' => ['group' => 'Velas e moderação', 'label' => 'Palavras bloqueadas', 'type' => 'textarea', 'default' => '', 'help' => 'Uma por linha ou separadas por vírgula. Textos com essas palavras ficam aguardando aprovação.'],
        'max_per_day' => ['group' => 'Velas e moderação', 'label' => 'Máximo de velas por pessoa por dia', 'type' => 'number', 'default' => 5, 'help' => 'Limite por conexão (IP), contra abusos.'],
        'mass_enabled' => ['group' => 'Velas e moderação', 'label' => 'Permitir "lembrar na Santa Missa"', 'type' => 'bool', 'default' => 1],
        'mass_note' => ['group' => 'Velas e moderação', 'label' => 'Aviso sobre as intenções de missa', 'type' => 'text', 'default' => 'As intenções são levadas ao altar e lembradas pelo padre durante a celebração.'],
        'wall_show_memorial' => ['group' => 'Velas e moderação', 'label' => 'Mostrar velas que já se apagaram no mural ("Memorial")', 'type' => 'bool', 'default' => 1],
        // ---- Pix ----
        'pix_key' => ['group' => 'Pix (velas pagas / ofertas)', 'label' => 'Chave Pix da igreja', 'type' => 'text', 'default' => '', 'help' => 'CPF/CNPJ, e-mail, celular (+55...) ou chave aleatória. Sem chave, só velas gratuitas são oferecidas.'],
        'pix_name' => ['group' => 'Pix (velas pagas / ofertas)', 'label' => 'Nome do recebedor (como no banco)', 'type' => 'text', 'default' => ''],
        'pix_city' => ['group' => 'Pix (velas pagas / ofertas)', 'label' => 'Cidade do recebedor', 'type' => 'text', 'default' => ''],
        'pix_instructions' => ['group' => 'Pix (velas pagas / ofertas)', 'label' => 'Instruções de pagamento', 'type' => 'textarea', 'default' => 'Após pagar, aguarde a confirmação pela nossa equipe: sua vela será acesa em seguida. Se quiser, envie o comprovante pelo WhatsApp da secretaria.'],
    ];

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /** @return array<string, mixed> todos os valores (padrão + gravados) */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $values = array_map(static fn ($d) => $d['default'], self::SCHEMA);
        foreach ($this->db->fetchAll('SELECT skey, svalue FROM settings') as $row) {
            $values[$row['skey']] = $row['svalue'];
        }
        return $this->cache = $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        // texto vazio salvo no painel é um valor válido (a pessoa quis apagar); só "nunca salvo" usa o padrão
        return $all[$key] ?? $default ?? self::SCHEMA[$key]['default'] ?? null;
    }

    /** Segredo aleatório do site (gerado uma vez): usado para anonimizar IPs e visitantes. */
    public function secret(): string
    {
        $value = (string) ($this->all()['app_secret'] ?? '');
        if ($value === '') {
            $value = bin2hex(random_bytes(32));
            $this->set('app_secret', $value);
        }
        return $value;
    }

    public function bool(string $key): bool
    {
        return (string) ($this->all()[$key] ?? '0') === '1';
    }

    public function set(string $key, ?string $value): void
    {
        $exists = $this->db->fetchValue('SELECT 1 FROM settings WHERE skey = ?', [$key]);
        if ($exists) {
            $this->db->update('settings', ['svalue' => $value, 'updated_at' => self::now()], 'skey = :k', ['k' => $key]);
        } else {
            $this->db->insert('settings', ['skey' => $key, 'svalue' => $value, 'updated_at' => self::now()]);
        }
        $this->cache = null;
    }

    /** Palavras bloqueadas como lista. */
    public function bannedWords(): array
    {
        $raw = (string) ($this->all()['banned_words'] ?? '');
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $raw) ?: [])));
    }
}
