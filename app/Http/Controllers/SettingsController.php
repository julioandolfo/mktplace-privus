<?php

namespace App\Http\Controllers;

use App\Models\AiProviderSetting;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $generalSettings = SystemSetting::getGroup('general');
        $aiProviders = AiProviderSetting::all();

        return view('settings.index', compact('generalSettings', 'aiProviders'));
    }

    public function update(Request $request)
    {
        $section = $request->input('section', 'general');

        if ($section === 'general') {
            $this->updateGeneralSettings($request);
        } elseif ($section === 'ai') {
            $this->updateAiSettings($request);
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
}
