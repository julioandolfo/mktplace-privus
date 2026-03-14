<?php

namespace App\Http\Controllers;

use App\Models\AiProviderSetting;
use App\Models\SystemSetting;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        $generalSettings     = SystemSetting::getGroup('general');
        $marketplaceSettings = SystemSetting::getGroup('marketplaces');
        $aiProviders         = AiProviderSetting::all();

        return view('settings.index', compact('generalSettings', 'marketplaceSettings', 'aiProviders'));
    }

    public function update(Request $request)
    {
        $section = $request->input('section', 'general');

        if ($section === 'general') {
            $this->updateGeneralSettings($request);
        } elseif ($section === 'logo') {
            $this->updateLogo($request);
        } elseif ($section === 'ai') {
            $this->updateAiSettings($request);
        } elseif ($section === 'marketplaces') {
            $this->updateMarketplaceSettings($request);
        }

        return redirect()->route('settings.index')
            ->with('success', 'Configuracoes atualizadas com sucesso.');
    }

    private function updateLogo(Request $request): void
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        // Remove old logo if exists
        $oldLogo = SystemSetting::get('general', 'logo_path');
        if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }

        $path = $request->file('logo')->store('logos', 'public');
        SystemSetting::set('general', 'logo_path', $path);
        SystemSetting::set('general', 'logo_url', Storage::url($path));
    }

    public function removeLogo()
    {
        $path = SystemSetting::get('general', 'logo_path');
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        SystemSetting::set('general', 'logo_path', null);
        SystemSetting::set('general', 'logo_url', null);

        return redirect()->route('settings.index')
            ->with('success', 'Logomarca removida com sucesso.');
    }

    private function updateGeneralSettings(Request $request): void
    {
        $fields = ['currency', 'timezone', 'date_format', 'system_name'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                SystemSetting::set('general', $field, $request->input($field));
            }
        }
    }

    private function updateAiSettings(Request $request): void
    {
        $validated = $request->validate([
            'provider' => 'required|in:openrouter,openai,anthropic',
            'api_key' => 'nullable|string',
            'default_model' => 'nullable|string',
            'monthly_budget_limit' => 'nullable|numeric|min:0',
        ]);

        $data = [
            'default_model' => $validated['default_model'],
            'monthly_budget_limit' => $validated['monthly_budget_limit'],
            'is_active' => true,
        ];

        // Só atualizar API key se o usuário digitou uma nova (não o placeholder)
        if (! empty($validated['api_key']) && ! str_contains($validated['api_key'], '••')) {
            $data['api_key'] = $validated['api_key'];
        }

        // Desativar outros providers
        AiProviderSetting::where('provider', '!=', $validated['provider'])->update(['is_active' => false]);

        AiProviderSetting::updateOrCreate(
            ['provider' => $validated['provider']],
            $data,
        );
    }

    public function testAiConnection(Request $request)
    {
        $provider = AiProviderSetting::where('is_active', true)->first();

        if (! $provider || empty($provider->api_key)) {
            return response()->json(['success' => false, 'message' => 'Nenhuma configuracao de IA ativa encontrada. Salve as configuracoes primeiro.']);
        }

        try {
            $ai = app(AiService::class);
            $response = $ai->generateText(
                'Responda apenas com "OK" sem nada mais.',
                'Teste de conexao.',
                10
            );

            return response()->json([
                'success' => true,
                'message' => 'Conexao bem-sucedida! Provedor: ' . $provider->provider . ' | Modelo: ' . ($provider->default_model ?? 'padrao'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Falha na conexao: ' . $e->getMessage(),
            ]);
        }
    }

    public function aiModels(Request $request)
    {
        // Aceitar provider via query param (ex: ?provider=openai) para preview antes de salvar
        $requestedProvider = $request->query('provider');

        if ($requestedProvider) {
            $provider = AiProviderSetting::where('provider', $requestedProvider)->first();
        } else {
            $provider = AiProviderSetting::where('is_active', true)->first();
        }

        if (! $provider || empty($provider->api_key)) {
            // Se não tem provider salvo mas pediu anthropic, retornar lista fixa mesmo sem key
            if ($requestedProvider === 'anthropic') {
                return response()->json([
                    ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'free' => false, 'context' => 200000],
                    ['id' => 'claude-haiku-4-20250414', 'name' => 'Claude Haiku 4', 'free' => false, 'context' => 200000],
                    ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'free' => false, 'context' => 200000],
                    ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku', 'free' => false, 'context' => 200000],
                    ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku', 'free' => false, 'context' => 200000],
                ]);
            }

            // OpenAI sem key — retornar lista fixa dos modelos populares
            if ($requestedProvider === 'openai') {
                return response()->json([
                    ['id' => 'gpt-4.1', 'name' => 'GPT-4.1', 'free' => false, 'context' => 1047576],
                    ['id' => 'gpt-4.1-mini', 'name' => 'GPT-4.1 Mini', 'free' => false, 'context' => 1047576],
                    ['id' => 'gpt-4.1-nano', 'name' => 'GPT-4.1 Nano', 'free' => false, 'context' => 1047576],
                    ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'free' => false, 'context' => 128000],
                    ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'free' => false, 'context' => 128000],
                    ['id' => 'o3', 'name' => 'o3', 'free' => false, 'context' => 200000],
                    ['id' => 'o3-mini', 'name' => 'o3-mini', 'free' => false, 'context' => 200000],
                    ['id' => 'o4-mini', 'name' => 'o4-mini', 'free' => false, 'context' => 200000],
                ]);
            }

            return response()->json([]);
        }

        $providerName = $requestedProvider ?? $provider->provider;

        try {
            if ($providerName === 'openrouter') {
                $response = Http::withToken($provider->api_key)
                    ->timeout(15)
                    ->get('https://openrouter.ai/api/v1/models');

                if ($response->failed()) {
                    return response()->json([]);
                }

                $models = collect($response->json('data') ?? [])
                    ->map(fn ($m) => [
                        'id' => $m['id'],
                        'name' => $m['name'] ?? $m['id'],
                        'free' => ($m['pricing']['prompt'] ?? '0') === '0' && ($m['pricing']['completion'] ?? '0') === '0',
                        'context' => $m['context_length'] ?? null,
                    ])
                    ->sortBy('name')
                    ->values();

                return response()->json($models);
            }

            if ($providerName === 'openai') {
                $response = Http::withToken($provider->api_key)
                    ->timeout(15)
                    ->get('https://api.openai.com/v1/models');

                if ($response->failed()) {
                    return response()->json([]);
                }

                $models = collect($response->json('data') ?? [])
                    ->filter(fn ($m) => str_starts_with($m['id'], 'gpt-') || str_starts_with($m['id'], 'o') || str_starts_with($m['id'], 'chatgpt'))
                    ->map(fn ($m) => [
                        'id' => $m['id'],
                        'name' => $m['id'],
                        'free' => false,
                        'context' => null,
                    ])
                    ->sortBy('name')
                    ->values();

                return response()->json($models);
            }

            if ($providerName === 'anthropic') {
                // Anthropic não tem endpoint de listagem de modelos — retornar lista fixa
                return response()->json([
                    ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'free' => false, 'context' => 200000],
                    ['id' => 'claude-haiku-4-20250414', 'name' => 'Claude Haiku 4', 'free' => false, 'context' => 200000],
                    ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'free' => false, 'context' => 200000],
                    ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku', 'free' => false, 'context' => 200000],
                    ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku', 'free' => false, 'context' => 200000],
                ]);
            }

            return response()->json([]);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    private function updateMarketplaceSettings(Request $request): void
    {
        // Campos de cada marketplace: [type => [field => label]]
        $fields = [
            'mercado_livre' => ['client_id', 'client_secret'],
            'amazon'        => ['client_id', 'client_secret'],
            'shopee'        => ['partner_id', 'partner_key'],
            'tiktok'        => ['app_id', 'app_secret'],
        ];

        foreach ($fields as $type => $keys) {
            foreach ($keys as $key) {
                $inputName = "{$type}_{$key}";
                if ($request->has($inputName)) {
                    $value = $request->input($inputName);
                    // Só atualiza se vier um valor novo (não apaga com placeholder "••••••••")
                    if ($value !== '' && $value !== '••••••••') {
                        SystemSetting::set('marketplaces', $inputName, $value);
                    }
                }
            }
        }
    }
}
