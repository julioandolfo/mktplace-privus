<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DesignerSettingsController extends Controller
{
    public function index()
    {
        $companyId = Auth::user()->company_id;

        if (! $companyId) {
            return redirect()->route('settings.index')
                ->with('error', 'Seu usuário ainda não está vinculado a uma empresa. Contate o administrador.');
        }

        $company = Company::findOrFail($companyId);

        $designers = User::where('company_id', $companyId)
            ->where('role', 'designer')
            ->withCount('designAssignments')
            ->get();

        $settings = $company->settings ?? [];
        $designerSettings = [
            'distribution'   => $settings['designer_distribution'] ?? 'round_robin',
            'designer_ids'   => $settings['designer_ids'] ?? [],
            'rr_pointer'     => $settings['rr_pointer'] ?? 0,
            'ai_enabled'     => $settings['ai_mockup_enabled'] ?? true,
            'ai_prompt'      => $settings['ai_mockup_prompt_prefix'] ?? '',
        ];

        return view('settings.designers.index', compact('designers', 'designerSettings', 'company'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'distribution' => 'required|in:round_robin,manual,random',
            'designer_ids' => 'nullable|array',
            'designer_ids.*' => 'exists:users,id',
            'ai_enabled'   => 'nullable|boolean',
            'ai_prompt'    => 'nullable|string|max:500',
        ]);

        $company   = Company::findOrFail(Auth::user()->company_id);
        $settings  = $company->settings ?? [];

        $settings['designer_distribution'] = $validated['distribution'];
        $settings['designer_ids']          = $validated['designer_ids'] ?? [];
        $settings['ai_mockup_enabled']     = (bool) ($validated['ai_enabled'] ?? true);
        $settings['ai_mockup_prompt_prefix'] = $validated['ai_prompt'] ?? '';

        $company->update(['settings' => $settings]);

        return back()->with('success', 'Configurações de designers salvas.');
    }

    /**
     * Convida (cria) um novo usuário designer na empresa.
     */
    public function inviteDesigner(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:150',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $designer = User::create([
            'company_id' => Auth::user()->company_id,
            'role'       => 'designer',
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Designer {$designer->name} criado com sucesso.");
    }

    /**
     * Ativa/desativa um designer (inclui/remove da lista do round-robin).
     */
    public function toggleDesigner(Request $request, User $user)
    {
        abort_unless($user->company_id === Auth::user()->company_id, 403);

        $company  = Company::findOrFail(Auth::user()->company_id);
        $settings = $company->settings ?? [];
        $activeIds = $settings['designer_ids'] ?? [];

        if (in_array($user->id, $activeIds)) {
            $activeIds = array_values(array_filter($activeIds, fn ($id) => $id !== $user->id));
            $msg = "{$user->name} removido do round-robin.";
        } else {
            $activeIds[] = $user->id;
            $msg = "{$user->name} adicionado ao round-robin.";
        }

        $settings['designer_ids'] = $activeIds;
        $company->update(['settings' => $settings]);

        return back()->with('success', $msg);
    }

    /**
     * Remove o usuário designer da empresa.
     */
    public function removeDesigner(User $user)
    {
        abort_unless($user->company_id === Auth::user()->company_id, 403);
        abort_unless($user->role === 'designer', 403);

        $user->delete();

        return back()->with('success', 'Designer removido.');
    }
}
