<?php

namespace App\Http\Controllers;

use App\Models\MelhorEnviosAccount;
use App\Services\MelhorEnviosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MelhorEnviosAccountController extends Controller
{
    public function index()
    {
        $accounts = MelhorEnviosAccount::where('company_id', Auth::user()->company_id)
            ->with('marketplaceAccounts')
            ->get();

        return view('settings.melhor-envios.index', compact('accounts'));
    }

    public function create()
    {
        return view('settings.melhor-envios.form', ['account' => new MelhorEnviosAccount()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $validated['company_id'] = Auth::user()->company_id;
        $validated['is_active']  = false; // ativo somente após OAuth

        MelhorEnviosAccount::create($validated);

        return redirect()->route('settings.me.index')
            ->with('success', 'Conta Melhor Envios criada. Agora clique em "Conectar" para autorizar via OAuth.');
    }

    public function show(MelhorEnviosAccount $melhorEnvio)
    {
        abort_unless($melhorEnvio->company_id === Auth::user()->company_id, 403);

        return redirect()->route('settings.me.edit', $melhorEnvio);
    }

    public function edit(MelhorEnviosAccount $melhorEnvio)
    {
        abort_unless($melhorEnvio->company_id === Auth::user()->company_id, 403);

        return view('settings.melhor-envios.form', ['account' => $melhorEnvio]);
    }

    public function update(Request $request, MelhorEnviosAccount $melhorEnvio)
    {
        abort_unless($melhorEnvio->company_id === Auth::user()->company_id, 403);

        $validated = $this->validateRequest($request);
        $melhorEnvio->update($validated);

        return redirect()->route('settings.me.index')
            ->with('success', 'Conta Melhor Envios atualizada.');
    }

    public function destroy(MelhorEnviosAccount $melhorEnvio)
    {
        abort_unless($melhorEnvio->company_id === Auth::user()->company_id, 403);

        $melhorEnvio->delete();

        return redirect()->route('settings.me.index')
            ->with('success', 'Conta removida.');
    }

    /**
     * Redireciona o usuário para a tela de autorização OAuth2 do Melhor Envios.
     */
    public function connect(MelhorEnviosAccount $melhorEnvio)
    {
        abort_unless($melhorEnvio->company_id === Auth::user()->company_id, 403);

        // Gera um state para CSRF e guarda o account_id na sessão
        $state = Str::random(40);
        session(['me_oauth_state' => $state, 'me_oauth_account_id' => $melhorEnvio->id]);

        $service = new MelhorEnviosService($melhorEnvio);
        $url     = $service->authorizationUrl(route('me.callback'), $state);

        return redirect($url);
    }

    /**
     * Recebe o callback OAuth2 do Melhor Envios, troca o code por tokens.
     */
    public function callback(Request $request)
    {
        // Valida state (CSRF)
        if ($request->input('state') !== session('me_oauth_state')) {
            return redirect()->route('settings.me.index')
                ->with('error', 'State OAuth inválido. Tente conectar novamente.');
        }

        $accountId = session('me_oauth_account_id');
        $account   = MelhorEnviosAccount::where('company_id', Auth::user()->company_id)
            ->findOrFail($accountId);

        if ($request->has('error')) {
            return redirect()->route('settings.me.index')
                ->with('error', 'Autorização negada pelo Melhor Envios: ' . $request->input('error_description', ''));
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect()->route('settings.me.index')
                ->with('error', 'Código de autorização não recebido.');
        }

        try {
            $service = new MelhorEnviosService($account);
            $service->exchangeCode($code, route('me.callback'));

            session()->forget(['me_oauth_state', 'me_oauth_account_id']);

            return redirect()->route('settings.me.index')
                ->with('success', "Conta \"{$account->name}\" conectada com sucesso ao Melhor Envios!");
        } catch (\Throwable $e) {
            return redirect()->route('settings.me.index')
                ->with('error', 'Erro ao trocar código: ' . $e->getMessage());
        }
    }

    private function validateRequest(Request $request): array
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'environment'    => 'required|in:production,sandbox',
            'client_id'      => 'nullable|string|max:100',
            'client_secret'  => 'nullable|string|max:200',
            // Remetente
            'from_name'      => 'nullable|string|max:100',
            'from_document'  => 'nullable|string|max:20',
            'from_cep'       => 'nullable|string|max:9',
            // Endereço de coleta (JSONB)
            'from_street'    => 'nullable|string|max:200',
            'from_number'    => 'nullable|string|max:10',
            'from_complement'=> 'nullable|string|max:100',
            'from_district'  => 'nullable|string|max=100',
            'from_city'      => 'nullable|string|max:100',
            'from_state'     => 'nullable|string|max:2',
            'from_phone'     => 'nullable|string|max:20',
            'from_email'     => 'nullable|email|max:150',
            // Dimensões padrão do pacote
            'pkg_weight'     => 'nullable|numeric|min:0.01',
            'pkg_width'      => 'nullable|integer|min:1',
            'pkg_height'     => 'nullable|integer|min:1',
            'pkg_length'     => 'nullable|integer|min:1',
        ]);

        // Monta JSONB from_address
        $fromAddress = array_filter([
            'street'      => $data['from_street']     ?? null,
            'number'      => $data['from_number']     ?? null,
            'complement'  => $data['from_complement'] ?? null,
            'neighborhood'=> $data['from_district']   ?? null,
            'city'        => $data['from_city']       ?? null,
            'state'       => $data['from_state']      ?? null,
            'phone'       => $data['from_phone']      ?? null,
            'email'       => $data['from_email']      ?? null,
        ]);

        // Monta JSONB default_package
        $defaultPkg = array_filter([
            'weight' => isset($data['pkg_weight']) ? (float) $data['pkg_weight'] : null,
            'width'  => isset($data['pkg_width'])  ? (int)   $data['pkg_width']  : null,
            'height' => isset($data['pkg_height']) ? (int)   $data['pkg_height'] : null,
            'length' => isset($data['pkg_length']) ? (int)   $data['pkg_length'] : null,
        ]);

        $result = [
            'name'          => $data['name'],
            'environment'   => $data['environment'],
            'from_name'     => $data['from_name']     ?? null,
            'from_document' => $data['from_document'] ?? null,
            'from_cep'      => preg_replace('/\D/', '', $data['from_cep'] ?? ''),
            'from_address'  => ! empty($fromAddress) ? $fromAddress : null,
            'default_package' => ! empty($defaultPkg) ? $defaultPkg : null,
        ];

        // Credenciais só atualiza se informadas
        if (! empty($data['client_id'])) {
            $result['client_id'] = $data['client_id'];
        }
        if (! empty($data['client_secret']) && $data['client_secret'] !== '••••••••') {
            $result['client_secret'] = $data['client_secret'];
        }

        return $result;
    }
}
