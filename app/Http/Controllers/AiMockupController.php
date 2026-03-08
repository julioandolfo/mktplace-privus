<?php

namespace App\Http\Controllers;

use App\Models\DesignAssignment;
use App\Models\DesignFile;
use App\Models\OrderTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiMockupController extends Controller
{
    /**
     * Gera mockup com IA usando OpenAI Image Editing (gpt-image-1).
     * Recebe: logo/arte upload + prompt opcional + imagem do produto (URL).
     */
    public function generate(Request $request, DesignAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $request->validate([
            'logo'        => 'required|file|image|max:10240', // 10 MB
            'prompt'      => 'nullable|string|max:500',
            'product_url' => 'nullable|url',
        ]);

        $apiKey = config('services.openai.key', env('OPENAI_API_KEY'));
        if (! $apiKey) {
            return response()->json(['error' => 'OpenAI API key não configurada.'], 422);
        }

        try {
            // Obter imagem do produto
            $productImagePath = $this->resolveProductImage($assignment, $request->input('product_url'));

            // Prompt padrão + customização do usuário
            $companySuffix = $assignment->order->company->settings['ai_mockup_prompt_prefix'] ?? '';
            $userPrompt    = $request->input('prompt', '');
            $basePrompt    = 'Realistic product mockup photo. Apply the provided logo/artwork naturally onto the product surface. '
                . 'Maintain the original product shape, lighting and shadows. Photorealistic result. '
                . ($companySuffix ? $companySuffix . ' ' : '')
                . ($userPrompt ? 'Additional instructions: ' . $userPrompt : '');

            // Chama OpenAI Images Edit
            $logoFile    = $request->file('logo');
            $logoContent = file_get_contents($logoFile->path());

            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->attach('image', file_get_contents($productImagePath), 'product.png', ['Content-Type' => 'image/png'])
                ->attach('mask', $logoContent, $logoFile->getClientOriginalName(), ['Content-Type' => $logoFile->getMimeType()])
                ->post('https://api.openai.com/v1/images/edits', [
                    'model'           => 'gpt-image-1',
                    'prompt'          => $basePrompt,
                    'n'               => 2,
                    'size'            => '1024x1024',
                    'response_format' => 'b64_json',
                ]);

            if (! $response->successful()) {
                Log::error('AiMockup OpenAI error: ' . $response->body());
                return response()->json([
                    'error' => 'Erro na API OpenAI: ' . ($response->json('error.message') ?? 'Tente novamente.'),
                ], 422);
            }

            // Salva as imagens geradas
            $images  = $response->json('data') ?? [];
            $results = [];

            foreach ($images as $idx => $imgData) {
                $imageData = base64_decode($imgData['b64_json'] ?? '');
                if (! $imageData) continue;

                $fileName = "ai-mockup-{$assignment->id}-" . now()->timestamp . "-{$idx}.png";
                $filePath = "designs/{$assignment->order_id}/ai/{$fileName}";
                Storage::disk('public')->put($filePath, $imageData);

                $results[] = [
                    'idx'  => $idx,
                    'url'  => Storage::disk('public')->url($filePath),
                    'path' => $filePath,
                ];
            }

            OrderTimeline::log(
                $assignment->order_id,
                'ai_mockup_generated',
                'Mockup gerado com IA',
                count($results) . ' variação(ões) gerada(s) via IA. Aguardando aprovação do designer.',
                ['prompt' => substr($basePrompt, 0, 200)],
            );

            return response()->json(['success' => true, 'images' => $results]);
        } catch (\Throwable $e) {
            Log::error('AiMockup exception: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao gerar mockup: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Aprova uma variação de mockup IA — cria DesignFile e retorna a URL para uso no canvas.
     */
    public function approve(Request $request, DesignAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $request->validate([
            'file_path' => 'required|string',
            'ai_prompt' => 'nullable|string',
        ]);

        $filePath = $request->input('file_path');
        $fileUrl  = Storage::disk('public')->url($filePath);

        // Lê o conteúdo para obter tamanho
        $fileContent = Storage::disk('public')->get($filePath);
        $fileSize    = strlen($fileContent ?? '');

        $designFile = DesignFile::create([
            'design_assignment_id' => $assignment->id,
            'uploaded_by'          => Auth::id(),
            'file_type'            => 'mockup',
            'file_name'            => basename($filePath),
            'file_path'            => $filePath,
            'file_url'             => $fileUrl,
            'mime_type'            => 'image/png',
            'file_size'            => $fileSize,
            'disk'                 => 'public',
            'is_production_file'   => false,
            'is_ai_generated'      => true,
            'ai_prompt'            => $request->input('ai_prompt'),
        ]);

        return response()->json([
            'success'    => true,
            'file_id'    => $designFile->id,
            'mockup_url' => $fileUrl,
            'message'    => 'Mockup IA aprovado e carregado no canvas.',
        ]);
    }

    private function resolveProductImage(DesignAssignment $assignment, ?string $urlOverride): string
    {
        if ($urlOverride) {
            // Baixa a imagem externa temporariamente
            $content = Http::get($urlOverride)->body();
            $tmpPath = storage_path('app/tmp/product-' . $assignment->id . '.png');
            file_put_contents($tmpPath, $content);
            return $tmpPath;
        }

        // Usa a imagem principal do primeiro produto do pedido
        $product = $assignment->order->items->first()?->product;
        $imgUrl  = $product?->primaryImage?->url ?? null;

        if ($imgUrl) {
            $content = Http::get($imgUrl)->body();
            $tmpPath = storage_path('app/tmp/product-' . $assignment->id . '.png');
            @mkdir(dirname($tmpPath), 0755, true);
            file_put_contents($tmpPath, $content);
            return $tmpPath;
        }

        throw new \RuntimeException('Nenhuma imagem de produto disponível para o mockup.');
    }

    private function authorizeAssignment(DesignAssignment $assignment): void
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $assignment->designer_id !== $user->id) {
            abort(403);
        }
        abort_unless($assignment->company_id === $user->company_id, 403);
    }
}
