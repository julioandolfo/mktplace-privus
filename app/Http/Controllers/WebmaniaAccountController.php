<?php

namespace App\Http\Controllers;

use App\Models\WebmaniaAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebmaniaAccountController extends Controller
{
    public function index()
    {
        $accounts = WebmaniaAccount::where('company_id', Auth::user()->company_id)
            ->with('marketplaceAccounts')
            ->get();

        return view('settings.webmania.index', compact('accounts'));
    }

    public function create()
    {
        return view('settings.webmania.form', ['account' => new WebmaniaAccount()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $validated['company_id'] = Auth::user()->company_id;

        WebmaniaAccount::create($validated);

        return redirect()->route('settings.webmania.index')
            ->with('success', 'Conta Webmaniabr criada com sucesso.');
    }

    public function edit(WebmaniaAccount $account)
    {
        abort_unless($account->company_id === Auth::user()->company_id, 403);

        return view('settings.webmania.form', compact('account'));
    }

    public function update(Request $request, WebmaniaAccount $account)
    {
        abort_unless($account->company_id === Auth::user()->company_id, 403);

        $validated = $this->validateRequest($request, $account->id);
        $account->update($validated);

        return redirect()->route('settings.webmania.index')
            ->with('success', 'Conta Webmaniabr atualizada com sucesso.');
    }

    public function destroy(WebmaniaAccount $account)
    {
        abort_unless($account->company_id === Auth::user()->company_id, 403);

        $account->delete();

        return redirect()->route('settings.webmania.index')
            ->with('success', 'Conta Webmaniabr removida.');
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'name'                      => 'required|string|max:100',
            'environment'               => 'required|in:producao,homologacao',
            'bearer_token'              => 'nullable|string',
            'consumer_key'              => 'nullable|string',
            'consumer_secret'           => 'nullable|string',
            'access_token'              => 'nullable|string',
            'access_token_secret'       => 'nullable|string',
            'is_active'                 => 'nullable|boolean',
            // Emissão
            'default_series'            => 'nullable|string|max:3',
            'default_cfop'              => 'nullable|string|max:10',
            'default_ncm'               => 'nullable|string|max:10',
            'default_cest'              => 'nullable|string|max:10',
            'default_tax_class'         => 'nullable|string|max:10',
            'default_nature_operation'  => 'nullable|string|max:60',
            'default_origin'            => 'nullable|string|max:1',
            'default_shipping_modality' => 'nullable|string|max:2',
            // Intermediador
            'intermediador_type'        => 'nullable|string',
            'intermediador_cnpj'        => 'nullable|string|max:20',
            'intermediador_id'          => 'nullable|string|max:60',
            // Textos NF-e
            'additional_info_fisco'     => 'nullable|string|max:2000',
            'additional_info_consumer'  => 'nullable|string|max:2000',
            // Automação
            'auto_emit_trigger'         => 'nullable|in:none,processing,completed',
            'auto_send_email'           => 'nullable|boolean',
            'emit_with_order_date'      => 'nullable|boolean',
            'error_email'               => 'nullable|email|max:100',
        ];

        $data = $request->validate($rules);

        // Normaliza booleanos
        $data['is_active']          = (bool) ($request->input('is_active') ?? false);
        $data['auto_send_email']    = (bool) ($request->input('auto_send_email') ?? false);
        $data['emit_with_order_date'] = (bool) ($request->input('emit_with_order_date') ?? false);

        // Remove tokens em branco (não sobrescreve com vazio)
        foreach (['bearer_token', 'consumer_key', 'consumer_secret', 'access_token', 'access_token_secret'] as $field) {
            if (empty($data[$field]) || $data[$field] === '••••••••') {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
