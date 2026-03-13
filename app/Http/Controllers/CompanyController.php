<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index()
    {
        return view('companies.index');
    }

    public function create()
    {
        return view('companies.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'document_type' => 'required|in:cpf,cnpj',
            'document' => 'required|string|max:20|unique:companies,document',
            'state_registration' => 'nullable|string|max:20',
            'municipal_registration' => 'nullable|string|max:20',
            'address' => 'nullable|array',
            'address.street' => 'nullable|string|max:255',
            'address.number' => 'nullable|string|max:20',
            'address.complement' => 'nullable|string|max:100',
            'address.neighborhood' => 'nullable|string|max:100',
            'address.city' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:2',
            'address.zip_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|max:10240',
        ]);

        $validated['document'] = preg_replace('/\D/', '', $validated['document']);
        unset($validated['logo']);

        if ($request->hasFile('logo')) {
            try {
                $validated['logo_path'] = $request->file('logo')->store('logos/companies', 'public');
            } catch (\Throwable $e) {
                Log::error('Company logo upload failed: ' . $e->getMessage());
                return back()->withInput()->with('error', 'Falha ao enviar logo: ' . $e->getMessage());
            }
        }

        Company::create($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Empresa cadastrada com sucesso.');
    }

    public function edit(Company $company)
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        // DEBUG: remove after fixing
        Log::info('[COMPANY UPDATE] Request received', [
            'company_id' => $company->id,
            'has_logo' => $request->hasFile('logo'),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'document_type' => 'required|in:cpf,cnpj',
            'document' => 'required|string|max:20|unique:companies,document,' . $company->id,
            'state_registration' => 'nullable|string|max:20',
            'municipal_registration' => 'nullable|string|max:20',
            'address' => 'nullable|array',
            'address.street' => 'nullable|string|max:255',
            'address.number' => 'nullable|string|max:20',
            'address.complement' => 'nullable|string|max:100',
            'address.neighborhood' => 'nullable|string|max:100',
            'address.city' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:2',
            'address.zip_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
            'logo' => 'nullable|image|max:10240',
        ]);

        $validated['document'] = preg_replace('/\D/', '', $validated['document']);

        // Remove file object — only logo_path should be persisted
        unset($validated['logo']);

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $validated['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            try {
                if ($company->logo_path) {
                    Storage::disk('public')->delete($company->logo_path);
                }
                $validated['logo_path'] = $request->file('logo')->store('logos/companies', 'public');
            } catch (\Throwable $e) {
                Log::error('Company logo upload failed: ' . $e->getMessage());
                return back()->withInput()->with('error', 'Falha ao enviar logo: ' . $e->getMessage());
            }
        }

        $company->update($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Empresa removida com sucesso.');
    }
}
