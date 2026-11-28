<?php
declare(strict_types=1);

namespace App\Admin;

use App\Models\CandlePlan;
use App\Models\CandleType;
use App\Models\DailyMessage;
use App\Models\MassTime;
use App\Models\Prayer;

/**
 * Cadastros simples do painel (CRUD genérico): cada entrada descreve a tabela, a lista e o formulário.
 * Tipos de campo: text, textarea, number, money, color, bool, select, date, time.
 * Para criar um novo cadastro basta acrescentar uma entrada aqui e um item no menu.
 */
final class Resources
{
    public const WEEKDAYS = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    /** @return array<string, array> */
    public static function all(): array
    {
        return [
            'tipos' => [
                'label' => 'Tipos de vela', 'singular' => 'tipo de vela', 'table' => 'candle_types', 'model' => CandleType::class,
                'admin_only' => true, 'order' => 'sort_order, id',
                'intro' => 'A cor aparece na vela do mural. O "valor extra" é somado ao valor da duração (use 0 para gratuita).',
                'columns' => [['name', 'Nome'], ['color', 'Cor', 'color'], ['description', 'Significado'], ['price', 'Valor extra', 'money'], ['active', 'Ativo', 'bool']],
                'fields' => [
                    'name' => ['label' => 'Nome', 'type' => 'text', 'rules' => 'required|max:60'],
                    'color' => ['label' => 'Cor da vela', 'type' => 'color', 'rules' => 'required|hexcolor', 'default' => '#F7F2E8'],
                    'description' => ['label' => 'Significado (aparece ao escolher)', 'type' => 'text', 'rules' => 'max:255', 'nullable' => true],
                    'price' => ['label' => 'Valor extra (R$)', 'type' => 'money', 'rules' => 'money', 'default' => 0, 'help' => 'Deixe 0,00 para vela gratuita. Valores exigem a chave Pix configurada.'],
                    'sort_order' => ['label' => 'Ordem de exibição', 'type' => 'number', 'rules' => 'between:0,9999', 'default' => 0],
                    'active' => ['label' => 'Disponível no site', 'type' => 'bool', 'default' => 1],
                ],
            ],
            'duracoes' => [
                'label' => 'Durações', 'singular' => 'duração', 'table' => 'candle_plans', 'model' => CandlePlan::class,
                'admin_only' => true, 'order' => 'sort_order, hours, id',
                'intro' => 'Quanto tempo a vela fica acesa e quanto custa. Durações com valor exigem a chave Pix configurada.',
                'columns' => [['label', 'Nome'], ['hours', 'Horas'], ['price', 'Valor', 'money'], ['highlight', 'Destaque', 'bool'], ['active', 'Ativo', 'bool']],
                'fields' => [
                    'label' => ['label' => 'Nome (ex.: 7 dias)', 'type' => 'text', 'rules' => 'required|max:60'],
                    'hours' => ['label' => 'Duração em horas', 'type' => 'number', 'rules' => 'required|between:1,8760', 'default' => 24, 'help' => '24 = um dia, 168 = sete dias, 720 = trinta dias.'],
                    'price' => ['label' => 'Valor (R$)', 'type' => 'money', 'rules' => 'money', 'default' => 0],
                    'highlight' => ['label' => 'Marcar como "mais escolhida"', 'type' => 'bool', 'default' => 0],
                    'sort_order' => ['label' => 'Ordem de exibição', 'type' => 'number', 'rules' => 'between:0,9999', 'default' => 0],
                    'active' => ['label' => 'Disponível no site', 'type' => 'bool', 'default' => 1],
                ],
            ],
            'oracoes' => [
                'label' => 'Orações', 'singular' => 'oração', 'table' => 'prayers', 'model' => Prayer::class,
                'order' => 'sort_order, title', 'slug_from' => 'title',
                'columns' => [['title', 'Título'], ['sort_order', 'Ordem'], ['active', 'Ativa', 'bool']],
                'fields' => [
                    'title' => ['label' => 'Título', 'type' => 'text', 'rules' => 'required|max:120'],
                    'body' => ['label' => 'Texto da oração', 'type' => 'textarea', 'rules' => 'required|max:6000', 'rows' => 12],
                    'sort_order' => ['label' => 'Ordem de exibição', 'type' => 'number', 'rules' => 'between:0,9999', 'default' => 50],
                    'active' => ['label' => 'Publicada no site', 'type' => 'bool', 'default' => 1],
                ],
            ],
            'mensagens' => [
                'label' => 'Palavra do dia', 'singular' => 'mensagem', 'table' => 'daily_messages', 'model' => DailyMessage::class,
                'order' => 'id DESC',
                'intro' => 'Sem data: entra no rodízio (uma por dia). Com data: aparece só naquele dia, no lugar do rodízio.',
                'columns' => [['text', 'Mensagem'], ['reference', 'Referência'], ['show_on', 'Data'], ['active', 'Ativa', 'bool']],
                'fields' => [
                    'text' => ['label' => 'Mensagem', 'type' => 'textarea', 'rules' => 'required|max:600', 'rows' => 4],
                    'reference' => ['label' => 'Referência (ex.: Salmo 23,1)', 'type' => 'text', 'rules' => 'max:80', 'nullable' => true],
                    'show_on' => ['label' => 'Mostrar somente neste dia (opcional)', 'type' => 'date', 'rules' => 'date', 'nullable' => true],
                    'active' => ['label' => 'Ativa', 'type' => 'bool', 'default' => 1],
                ],
            ],
            'horarios' => [
                'label' => 'Horários das missas', 'singular' => 'horário', 'table' => 'mass_times', 'model' => MassTime::class,
                'order' => 'weekday, time, sort_order',
                'columns' => [['weekday', 'Dia', 'weekday'], ['time', 'Horário'], ['label', 'Celebração'], ['location', 'Local'], ['active', 'Ativo', 'bool']],
                'fields' => [
                    'weekday' => ['label' => 'Dia da semana', 'type' => 'select', 'options' => self::WEEKDAYS, 'rules' => 'required|in:0,1,2,3,4,5,6', 'default' => 0],
                    'time' => ['label' => 'Horário (HH:MM)', 'type' => 'time', 'rules' => 'required|time', 'default' => '19:00'],
                    'label' => ['label' => 'Celebração', 'type' => 'text', 'rules' => 'required|max:100', 'default' => 'Santa Missa'],
                    'location' => ['label' => 'Local (opcional)', 'type' => 'text', 'rules' => 'max:120', 'nullable' => true],
                    'sort_order' => ['label' => 'Ordem', 'type' => 'number', 'rules' => 'between:0,9999', 'default' => 0],
                    'active' => ['label' => 'Ativo', 'type' => 'bool', 'default' => 1],
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
