<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validação declarativa: ['email' => 'required|email|max:120'].
 * Regras: required, email, min, max, int, between, in, date, time, url, phone, confirmed, password,
 *         regex, accepted, nullable, hexcolor, money
 */
final class Validator
{
    private array $errors = [];

    private const LABELS = [
        'name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'current_password' => 'senha atual',
        'type_id' => 'tipo de vela', 'plan_id' => 'duração', 'category' => 'motivo', 'for_whom' => 'por quem',
        'intention' => 'intenção', 'terms' => 'aceite da política de privacidade', 'title' => 'título',
        'body' => 'texto', 'text' => 'texto', 'reference' => 'referência', 'label' => 'nome', 'hours' => 'duração (horas)',
        'price' => 'valor', 'color' => 'cor', 'description' => 'descrição', 'sort_order' => 'ordem', 'show_on' => 'data',
        'time' => 'horário', 'weekday' => 'dia da semana', 'location' => 'local', 'role' => 'perfil', 'code' => 'código',
    ];

    public function __construct(private readonly array $data)
    {
    }

    public static function make(array $data, array $rules): self
    {
        $v = new self($data);
        $v->run($rules);
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        foreach ($this->errors as $messages) {
            return $messages[0];
        }
        return '';
    }

    private function run(array $rules): void
    {
        foreach ($rules as $field => $ruleString) {
            $value = $this->data[$field] ?? '';
            $value = is_string($value) ? $value : '';
            $list = explode('|', $ruleString);
            if ($value === '' && !in_array('required', $list, true) && !in_array('accepted', $list, true)) {
                continue; // campo opcional vazio
            }
            foreach ($list as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($field, $name, $arg, $value);
                if ($error !== null) {
                    $this->errors[$field][] = $error;
                    break;
                }
            }
        }
    }

    private function check(string $field, string $rule, ?string $arg, string $value): ?string
    {
        $label = self::LABELS[$field] ?? $field;
        $len = mb_strlen($value);
        return match ($rule) {
            'nullable' => null,
            'required' => $value === '' ? "Preencha o campo {$label}." : null,
            'accepted' => in_array($value, ['1', 'on', 'yes'], true) ? null : "É preciso marcar o {$label}.",
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) && $len <= 160 ? null : 'Informe um e-mail válido.',
            'min' => $len < (int) $arg ? "O campo {$label} deve ter no mínimo {$arg} caracteres." : null,
            'max' => $len > (int) $arg ? "O campo {$label} deve ter no máximo {$arg} caracteres." : null,
            'int' => preg_match('/^\d{1,9}$/', $value) ? null : "O campo {$label} deve ser um número inteiro.",
            'between' => $this->between($value, (string) $arg) ? null : "O campo {$label} está fora do intervalo permitido ({$arg}).",
            'in' => in_array($value, explode(',', (string) $arg), true) ? null : "Valor inválido em {$label}.",
            'date' => $this->isDate($value, 'Y-m-d') ? null : "Data inválida em {$label}.",
            'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? null : "Horário inválido (use HH:MM) em {$label}.",
            'url' => $this->isHttpUrl($value) ? null : "Informe um link válido (https://...) em {$label}.",
            'phone' => preg_match('/^[\d\s()+-]{8,20}$/', $value) ? null : "Telefone inválido em {$label}.",
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value ? null : 'A confirmação da senha não confere.',
            'password' => $this->strongPassword($value) ? null : 'A senha precisa ter 8+ caracteres, com letras e números.',
            'regex' => preg_match((string) $arg, $value) ? null : "Formato inválido em {$label}.",
            'hexcolor' => preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? null : "Use uma cor no formato #RRGGBB em {$label}.",
            'money' => preg_match('/^\d{1,6}([.,]\d{1,2})?$/', $value) ? null : "Informe um valor válido (ex.: 5,00) em {$label}.",
            default => throw new \InvalidArgumentException("Regra desconhecida: {$rule}"),
        };
    }

    private function between(string $value, string $arg): bool
    {
        [$min, $max] = array_map('intval', explode(',', $arg));
        return preg_match('/^\d{1,9}$/', $value) && (int) $value >= $min && (int) $value <= $max;
    }

    private function isDate(string $value, string $format): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        return $d !== false && $d->format($format) === $value;
    }

    private function isHttpUrl(string $value): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL) || mb_strlen($value) > 500) {
            return false;
        }
        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function strongPassword(string $value): bool
    {
        return mb_strlen($value) >= 8 && mb_strlen($value) <= 128
            && preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value);
    }
}
