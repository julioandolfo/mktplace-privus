<?php

namespace App\Http\Controllers;

use App\Models\AiProviderSetting;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

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
        } elseif ($section === 'ai') {
            $this->updateAiSettings($request);
        } elseif ($section === 'marketplaces') {
            $this->updateMarketplaceSettings($request);
        }

        return redirect()->route('settings.index')
            ->with('success', 'Configuracoes atualizadas com sucesso.');
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
