<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    public function index()
    {
        $users = User::where('company_id', Auth::user()->company_id)
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 WHEN role = 'operator' THEN 1 WHEN role = 'designer' THEN 2 END")
            ->orderBy('name')
            ->get();

        return view('settings.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:150',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:admin,operator,designer',
        ]);

        User::create([
            'company_id' => Auth::user()->company_id,
            'role'       => $validated['role'],
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Usuário {$validated['name']} criado com sucesso.");
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->company_id === Auth::user()->company_id, 403);

        $validated = $request->validate([
            'name'     => 'required|string|max:150',
            'email'    => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role'     => 'required|in:admin,operator,designer',
        ]);

        $data = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'role'  => $validated['role'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return back()->with('success', "Usuário {$user->name} atualizado com sucesso.");
    }

    public function destroy(User $user)
    {
        abort_unless($user->company_id === Auth::user()->company_id, 403);
        abort_if($user->id === Auth::id(), 403);

        $name = $user->name;
        $user->delete();

        return back()->with('success', "Usuário {$name} removido com sucesso.");
    }
}
