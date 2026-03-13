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
            'logo_base64' => 'nullable|string',
        ]);

        $validated['document'] = preg_replace('/\D/', '', $validated['document']);
        unset($validated['logo_base64']);

        if ($request->filled('logo_base64')) {
            $validated['logo_path'] = $this->storeBase64Logo($request->input('logo_base64'));
            if (! $validated['logo_path']) {
                return back()->withInput()->with('error', 'Falha ao processar logo. Verifique o formato da imagem.');
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
            'logo_base64' => 'nullable|string',
        ]);

        $validated['document'] = preg_replace('/\D/', '', $validated['document']);
        unset($validated['logo_base64']);

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $validated['logo_path'] = null;
        } elseif ($request->filled('logo_base64')) {
            $newPath = $this->storeBase64Logo($request->input('logo_base64'));
            if ($newPath) {
                if ($company->logo_path) {
                    Storage::disk('public')->delete($company->logo_path);
                }
                $validated['logo_path'] = $newPath;
            } else {
                return back()->withInput()->with('error', 'Falha ao processar logo. Verifique o formato da imagem.');
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

    /**
     * Decode a data-URI base64 string and store it as a file.
     * Returns the stored path or null on failure.
     */
    private function storeBase64Logo(string $dataUri): ?string
    {
        try {
            if (! preg_match('/^data:image\/(png|jpe?g|webp|svg\+xml);base64,(.+)$/i', $dataUri, $m)) {
                Log::warning('Logo base64: invalid data-URI format');
                return null;
            }

            $ext = str_replace(['jpeg', 'svg+xml'], ['jpg', 'svg'], strtolower($m[1]));
            $binary = base64_decode($m[2], true);

            if ($binary === false || strlen($binary) > 2 * 1024 * 1024) {
                Log::warning('Logo base64: decode failed or file too large');
                return null;
            }

            $filename = 'logos/companies/' . uniqid('logo_') . '.' . $ext;
            Storage::disk('public')->put($filename, $binary);

            return $filename;
        } catch (\Throwable $e) {
            Log::error('Logo base64 store failed: ' . $e->getMessage());
            return null;
        }
    }
}
