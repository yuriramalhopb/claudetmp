<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validação declarativa: ['email' => 'required|email|max:120'].
 * Regras: required, email, min, max, int, between, in, date, datetime, url, phone,
 *         confirmed, password, regex, instagram_handle, accepted, nullable
 */
final class Validator
{
    private array $errors = [];

    private const LABELS = [
        'name' => 'nome', 'nickname' => 'apelido', 'email' => 'e-mail', 'password' => 'senha',
        'phone' => 'telefone', 'city' => 'cidade', 'birth_date' => 'data de nascimento',
        'bike_brand' => 'marca da moto', 'bike_model' => 'modelo da moto', 'bike_cc' => 'cilindrada',
        'bike_year' => 'ano da moto', 'experience' => 'nível', 'blood_type' => 'tipo sanguíneo',
        'emergency_name' => 'contato de emergência', 'emergency_phone' => 'telefone de emergência',
        'instagram' => 'Instagram', 'bio' => 'sobre você', 'title' => 'título', 'description' => 'descrição',
        'start_at' => 'data de início', 'end_at' => 'data de término', 'location' => 'local',
        'organizer' => 'organizador', 'level' => 'nível', 'distance_km' => 'distância', 'price' => 'valor',
        'external_link' => 'link', 'message' => 'mensagem', 'caption' => 'legenda', 'permalink' => 'link do post',
        'lgpd' => 'termo de privacidade', 'current_password' => 'senha atual', 'album_id' => 'álbum',
        'role' => 'perfil', 'state' => 'estado', 'category' => 'categoria', 'instagram_url' => 'Instagram do evento',
        'whatsapp' => 'WhatsApp', 'capacity' => 'vagas', 'includes' => 'o que está incluso',
        'registration_until' => 'inscrições até', 'status' => 'situação', 'access_token' => 'token', 'username' => 'usuário',
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
            'required' => $value === '' ? "O campo {$label} é obrigatório." : null,
            'accepted' => in_array($value, ['1', 'on', 'yes'], true) ? null : "Você precisa aceitar o {$label}.",
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) && $len <= 160 ? null : 'Informe um e-mail válido.',
            'min' => $len < (int) $arg ? "O campo {$label} deve ter no mínimo {$arg} caracteres." : null,
            'max' => $len > (int) $arg ? "O campo {$label} deve ter no máximo {$arg} caracteres." : null,
            'int' => preg_match('/^\d{1,9}$/', $value) ? null : "O campo {$label} deve ser um número.",
            'between' => $this->between($value, (string) $arg) ? null : "O campo {$label} está fora do intervalo permitido ({$arg}).",
            'in' => in_array($value, explode(',', (string) $arg), true) ? null : "Valor inválido para {$label}.",
            'date' => $this->isDate($value, 'Y-m-d') ? null : "Data inválida em {$label}.",
            'datetime' => $this->isDate($value, 'Y-m-d\TH:i') ? null : "Data/hora inválida em {$label}.",
            'url' => $this->isHttpUrl($value) ? null : "Informe um link válido (https://...) em {$label}.",
            'phone' => preg_match('/^[\d\s()+-]{8,20}$/', $value) ? null : "Telefone inválido em {$label}.",
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value ? null : 'A confirmação de senha não confere.',
            'password' => $this->strongPassword($value) ? null : 'A senha precisa ter 8+ caracteres, com letras e números.',
            'instagram_handle' => preg_match('/^@?[A-Za-z0-9._]{1,30}$/', $value) ? null : 'Usuário do Instagram inválido.',
            'regex' => preg_match((string) $arg, $value) ? null : "Formato inválido em {$label}.",
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
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    private function strongPassword(string $value): bool
    {
        return mb_strlen($value) >= 8 && mb_strlen($value) <= 128
            && preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value);
    }
}
