<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Termo de responsabilidade aceito pelo trilheiro ao confirmar presença.
 * O hash do texto aceito fica registrado junto com data/hora e IP: se o texto
 * mudar depois, continua sendo possível saber qual versão cada um aceitou.
 */
final class Waiver
{
    public const DEFAULT_TEXT = <<<'TXT'
TERMO DE RESPONSABILIDADE E CIÊNCIA DE RISCOS

Ao confirmar presença, declaro que:

1. Participo por livre e espontânea vontade de atividade de trilha/off-road, que envolve riscos de quedas, lesões e danos materiais, inclusive em locais de difícil acesso e sem atendimento médico imediato.
2. Estou habilitado(a) para conduzir motocicleta (CNH categoria A) e minha moto está em condições adequadas de manutenção e segurança.
3. Utilizarei equipamentos de proteção individual: capacete, óculos, botas, joelheiras, cotoveleiras, luvas e colete.
4. Estou em boas condições de saúde e não pilotarei sob efeito de álcool, drogas ou medicamentos que reduzam a atenção.
5. Seguirei as orientações da organização, respeitarei o ritmo do grupo, o meio ambiente, as propriedades e os moradores dos locais por onde passarmos.
6. Assumo a responsabilidade por danos que eu causar a terceiros, a mim mesmo e ao meu veículo, isentando a organização e os demais participantes, exceto em caso de dolo.
7. Autorizo o uso de imagens feitas durante o evento na divulgação da turma (site e redes sociais).
8. Autorizo a organização a usar meus dados de saúde e contato de emergência exclusivamente para prestar socorro em caso de acidente.
TXT;

    public function __construct(private readonly Setting $settings)
    {
    }

    public function text(): string
    {
        return (string) ($this->settings->get('waiver_text') ?: self::DEFAULT_TEXT);
    }

    public function hash(): string
    {
        return hash('sha256', $this->text());
    }

    public function update(string $text): void
    {
        $this->settings->set('waiver_text', $text);
        // guarda as versões antigas para consulta (hash => texto)
        $history = json_decode((string) $this->settings->get('waiver_history', '{}'), true) ?: [];
        $history[hash('sha256', $text)] = ['text' => $text, 'since' => date('Y-m-d H:i:s')];
        $this->settings->set('waiver_history', (string) json_encode($history, JSON_UNESCAPED_UNICODE));
    }

    public function versionText(string $hash): ?string
    {
        if (hash_equals(hash('sha256', self::DEFAULT_TEXT), $hash)) {
            return self::DEFAULT_TEXT;
        }
        $history = json_decode((string) $this->settings->get('waiver_history', '{}'), true) ?: [];
        return $history[$hash]['text'] ?? null;
    }
}
