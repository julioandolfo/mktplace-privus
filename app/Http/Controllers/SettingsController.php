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

        AiProviderSetting::updateOrCreate(
            ['provider' => $validated['provider']],
            [
                'api_key' => $validated['api_key'],
                'default_model' => $validated['default_model'],
                'monthly_budget_limit' => $validated['monthly_budget_limit'],
                'is_active' => true,
            ]
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
        $provider = AiProviderSetting::where('is_active', true)->first();

        if (! $provider || empty($provider->api_key)) {
            return response()->json([]);
        }

        try {
            if ($provider->provider === 'openrouter') {
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
