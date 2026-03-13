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
            // Expected format: data:image/png;base64,iVBOR...
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

    // ── DEBUG methods — remove after fixing ─────────────────────

    public function debugUploadForm()
    {
        $html = '<html><body style="font-family:sans-serif;padding:40px;background:#1a1a2e;color:#eee">'
            . '<h2>Teste de Upload</h2>'
            . '<p>PHP upload_max_filesize: ' . ini_get('upload_max_filesize') . '</p>'
            . '<p>PHP post_max_size: ' . ini_get('post_max_size') . '</p>'
            . '<p>Storage writable: ' . (is_writable(storage_path('app/public')) ? 'YES' : 'NO') . '</p>'
            . '<p>Temp dir: ' . sys_get_temp_dir() . ' writable: ' . (is_writable(sys_get_temp_dir()) ? 'YES' : 'NO') . '</p>'
            . '<hr>'
            . '<h3>1. POST com arquivo</h3>'
            . '<form method="POST" action="/debug-upload" enctype="multipart/form-data">'
            . csrf_field()
            . '<input type="file" name="logo" accept="image/*"><br><br>'
            . '<button type="submit" style="padding:10px 20px;background:#7c3aed;color:#fff;border:none;border-radius:6px;cursor:pointer">Enviar com arquivo</button>'
            . '</form>'
            . '<hr>'
            . '<h3>2. POST sem arquivo (teste controle)</h3>'
            . '<form method="POST" action="/debug-upload" enctype="multipart/form-data">'
            . csrf_field()
            . '<input type="hidden" name="test" value="no-file">'
            . '<button type="submit" style="padding:10px 20px;background:#059669;color:#fff;border:none;border-radius:6px;cursor:pointer">Enviar SEM arquivo</button>'
            . '</form>'
            . '</body></html>';

        return response($html);
    }

    public function debugUploadPost(Request $request)
    {
        $info = [
            'timestamp' => now()->toDateTimeString(),
            'has_file' => $request->hasFile('logo'),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'php_upload_max' => ini_get('upload_max_filesize'),
            'php_post_max' => ini_get('post_max_size'),
            'temp_dir' => sys_get_temp_dir(),
            'temp_writable' => is_writable(sys_get_temp_dir()),
            'storage_writable' => is_writable(storage_path('app/public')),
            'all_input_keys' => array_keys($request->all()),
            'all_files' => array_keys($request->allFiles()),
        ];

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $info['file_name'] = $file->getClientOriginalName();
            $info['file_size'] = $file->getSize();
            $info['file_mime'] = $file->getMimeType();
            $info['file_valid'] = $file->isValid();
            $info['file_error'] = $file->getError();
            try {
                $path = $file->store('logos/debug', 'public');
                $info['stored_path'] = $path;
                $info['result'] = 'SUCCESS';
            } catch (\Throwable $e) {
                $info['error'] = $e->getMessage();
                $info['result'] = 'FAILED';
            }
        } else {
            $info['result'] = 'NO FILE - but POST worked!';
        }

        return response()->json($info, 200, [], JSON_PRETTY_PRINT);
    }

    public function debugLog()
    {
        $logFile = storage_path('logs/laravel.log');
        if (! file_exists($logFile)) {
            return response('<pre>Log file not found at: ' . $logFile . "\n\nExisting files:\n" . implode("\n", glob(storage_path('logs/*'))) . '</pre>');
        }

        $lines = file($logFile);
        $tail = array_slice($lines, -150);

        return response('<html><body style="font-family:monospace;padding:20px;background:#111;color:#0f0;font-size:12px;white-space:pre-wrap">'
            . htmlspecialchars(implode('', $tail))
            . '</body></html>');
    }
}
