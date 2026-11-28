<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Setting;
use App\Services\Pix;

/** Configurações do site e da igreja: o formulário nasce de Setting::SCHEMA. */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = [];
        foreach (Setting::SCHEMA as $key => $def) {
            $groups[$def['group']][$key] = $def;
        }
        return $this->adminView('settings', ['title' => 'Configurações', 'groups' => $groups, 'values' => $this->app->settings()->all()]);
    }

    public function update(Request $request): Response
    {
        $input = [];
        $rules = [];
        foreach (Setting::SCHEMA as $key => $def) {
            $input[$key] = $def['type'] === 'bool' ? ($request->bool($key) ? '1' : '0') : $request->input($key);
            $rules[$key] = match ($def['type']) {
                'email' => 'email',
                'url' => 'url',
                'number' => 'between:1,100',
                'select' => 'required|in:' . implode(',', array_keys($def['options'])),
                'bool' => 'in:0,1',
                'textarea' => 'max:5000',
                default => 'max:250',
            };
        }
        if ($fail = $this->validate($input, $rules, '/admin/configuracoes')) {
            return $fail;
        }
        if ($input['pix_key'] !== '' && !Pix::validKey($input['pix_key'])) {
            return $this->failWith(['pix_key' => ['Chave Pix inválida. Use CPF/CNPJ (só números), e-mail, celular com +55 ou chave aleatória.']], $input, '/admin/configuracoes');
        }
        $settings = $this->app->settings();
        foreach ($input as $key => $value) {
            $settings->set($key, $value);
        }
        $this->app->audit('configuracoes_salvas');
        $this->flash('success', 'Configurações salvas.');
        return $this->redirect('/admin/configuracoes');
    }
}
