<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Admin\Resources;
use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * CRUD genérico dirigido por App\Admin\Resources (tipos de vela, durações, orações,
 * palavra do dia, horários de missa).
 */
final class ResourceController extends Controller
{
    private function resource(Request $request): array
    {
        $key = (string) $request->param('resource');
        $res = Resources::get($key);
        if ($res === null) {
            throw new HttpException(404);
        }
        if (!empty($res['admin_only']) && !$this->auth->isAdmin()) {
            throw new HttpException(403);
        }
        return [$key, $res];
    }

    public function index(Request $request): Response
    {
        [$key, $res] = $this->resource($request);
        $rows = $this->db->fetchAll("SELECT * FROM {$res['table']} ORDER BY {$res['order']}");
        return $this->adminView('resource/index', ['key' => $key, 'res' => $res, 'rows' => $rows, 'title' => $res['label']]);
    }

    public function create(Request $request): Response
    {
        [$key, $res] = $this->resource($request);
        $defaults = array_map(static fn ($f) => $f['default'] ?? '', $res['fields']);
        return $this->adminView('resource/form', ['key' => $key, 'res' => $res, 'row' => null, 'defaults' => $defaults, 'title' => 'Novo: ' . $res['singular']]);
    }

    public function edit(Request $request): Response
    {
        [$key, $res] = $this->resource($request);
        $row = $this->notFoundIfNull($this->db->fetch("SELECT * FROM {$res['table']} WHERE id = ?", [$this->idParam($request)]));
        return $this->adminView('resource/form', ['key' => $key, 'res' => $res, 'row' => $row, 'defaults' => $row, 'title' => 'Editar: ' . $res['singular']]);
    }

    public function store(Request $request): Response
    {
        [$key, $res] = $this->resource($request);
        return $this->save($request, $key, $res, null);
    }

    public function update(Request $request): Response
    {
        [$key, $res] = $this->resource($request);
        $row = $this->notFoundIfNull($this->db->fetch("SELECT * FROM {$res['table']} WHERE id = ?", [$this->idParam($request)]));
        return $this->save($request, $key, $res, $row);
    }

    private function save(Request $request, string $key, array $res, ?array $row): Response
    {
        $back = "/admin/{$key}" . ($row ? "/{$row['id']}/editar" : '/novo');
        $input = $request->only(array_keys($res['fields']));
        $rules = [];
        foreach ($res['fields'] as $name => $f) {
            if (isset($f['rules'])) {
                $rules[$name] = $f['rules'];
            }
        }
        // campos "bool" nunca vêm vazios no Validator; checkbox desmarcado simplesmente não é enviado
        if ($fail = $this->validate($input, $rules, $back)) {
            return $fail;
        }
        $data = [];
        foreach ($res['fields'] as $name => $f) {
            $value = $input[$name] ?? '';
            $data[$name] = match ($f['type']) {
                'bool' => $request->bool($name) ? 1 : 0,
                'number' => $value === '' ? 0 : (int) $value,
                'money' => to_cents($value),
                default => ($value === '' && !empty($f['nullable'])) ? null : $value,
            };
        }
        if ($row === null) {
            $data['created_at'] = date('Y-m-d H:i:s');
            if (!empty($res['slug_from'])) {
                $data['slug'] = $this->uniqueSlug($res['table'], slugify((string) $input[$res['slug_from']]));
            }
            $id = $this->db->insert($res['table'], $data);
            $this->app->audit('cadastro_criado', "{$res['singular']} #{$id}");
            $this->flash('success', ucfirst($res['singular']) . ' criado(a).');
        } else {
            $this->db->update($res['table'], $data, 'id = :id', ['id' => (int) $row['id']]);
            $this->app->audit('cadastro_editado', "{$res['singular']} #{$row['id']}");
            $this->flash('success', 'Alterações salvas.');
        }
        return $this->redirect("/admin/{$key}");
    }

    public function delete(Request $request): Response
    {
        [$key, $res] = $this->resource($request);
        $id = $this->idParam($request);
        try {
            $this->db->execute("DELETE FROM {$res['table']} WHERE id = ?", [$id]);
            $this->app->audit('cadastro_excluido', "{$res['singular']} #{$id}");
            $this->flash('success', 'Item excluído.');
        } catch (\PDOException $e) {
            // chave estrangeira: já existem velas usando este tipo
            $this->flash('error', 'Este item já foi usado em velas e não pode ser excluído. Desmarque "Disponível no site" para escondê-lo.');
        }
        return $this->redirect("/admin/{$key}");
    }

    private function uniqueSlug(string $table, string $base): string
    {
        $slug = $base;
        for ($i = 2; $this->db->fetchValue("SELECT 1 FROM {$table} WHERE slug = ?", [$slug]); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }
}
