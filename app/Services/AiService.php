<?php

namespace App\Services;

use App\Models\AiProviderSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    private ?AiProviderSetting $provider;

    public function __construct()
    {
        $this->provider = AiProviderSetting::where('is_active', true)->first();
    }

    public function isConfigured(): bool
    {
        return $this->provider !== null && ! empty($this->provider->api_key);
    }

    public function getProviderName(): string
    {
        return $this->provider?->provider ?? 'openrouter';
    }

    private function chatBaseUrl(): string
    {
        return match ($this->provider?->provider) {
            'openai'    => 'https://api.openai.com/v1',
            'anthropic' => 'https://api.anthropic.com/v1',
            default     => 'https://openrouter.ai/api/v1',
        };
    }

    private function imageBaseUrl(): string
    {
        // Image generation: OpenAI format (/images/generations)
        // OpenRouter supports image gen with specific models too
        return match ($this->provider?->provider) {
            'openai' => 'https://api.openai.com/v1',
            default  => 'https://openrouter.ai/api/v1',
        };
    }

    private function defaultChatModel(): string
    {
        return $this->provider?->default_model
            ?? match ($this->provider?->provider) {
                'openai'    => 'gpt-4o-mini',
                'anthropic' => 'claude-haiku-20240307',
                default     => 'anthropic/claude-haiku-20240307',
            };
    }

    /**
     * Generate text via chat completions (OpenAI-compatible API).
     * Works with OpenRouter, OpenAI, and OpenAI-compatible providers.
     */
    public function generateText(string $systemPrompt, string $userPrompt, int $maxTokens = 2000): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('IA não configurada. Acesse Configurações → Inteligência Artificial e adicione sua chave API.');
        }

        $model = $this->defaultChatModel();

        if ($this->provider->provider === 'anthropic') {
            return $this->generateTextAnthropic($systemPrompt, $userPrompt, $model, $maxTokens);
        }

        $response = Http::withToken($this->provider->api_key)
            ->timeout(60)
            ->withHeaders([
                'HTTP-Referer' => config('app.url', 'https://privus.com.br'),
                'X-Title'      => config('app.name', 'Privus Marketplace'),
            ])
            ->post($this->chatBaseUrl() . '/chat/completions', [
                'model'      => $model,
                'messages'   => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'max_tokens'  => $maxTokens,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->json('error') ?? $response->body();
            Log::error('AiService generateText error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Erro na IA: ' . (is_string($error) ? $error : json_encode($error)));
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }

    private function generateTextAnthropic(string $systemPrompt, string $userPrompt, string $model, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->provider->api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $model,
                'max_tokens' => $maxTokens,
                'system'     => $systemPrompt,
                'messages'   => [['role' => 'user', 'content' => $userPrompt]],
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException('Erro na IA (Anthropic): ' . $error);
        }

        return trim($response->json('content.0.text') ?? '');
    }

    /**
     * Generate an image using DALL-E (OpenAI / OpenRouter with image model).
     * Returns a public URL for the generated image.
     */
    public function generateImage(string $prompt, string $size = '1024x1024'): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('IA não configurada. Acesse Configurações → Inteligência Artificial e adicione sua chave API.');
        }

        $imageModel = $this->provider->settings['image_model'] ?? 'dall-e-3';

        $response = Http::withToken($this->provider->api_key)
            ->timeout(120)
            ->withHeaders([
                'HTTP-Referer' => config('app.url', 'https://privus.com.br'),
                'X-Title'      => config('app.name', 'Privus Marketplace'),
            ])
            ->post($this->imageBaseUrl() . '/images/generations', [
                'model'   => $imageModel,
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => $size,
                'quality' => 'standard',
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->json('error') ?? $response->body();
            Log::error('AiService generateImage error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Erro ao gerar imagem: ' . (is_string($error) ? $error : json_encode($error)));
        }

        $url = $response->json('data.0.url');
        if (! $url) {
            throw new \RuntimeException('A IA não retornou uma imagem. Verifique se seu modelo suporta geração de imagens (ex: dall-e-3).');
        }

        return $url;
    }

    /**
     * Build a description generation prompt for a marketplace listing.
     */
    public static function buildDescriptionPrompt(string $title, string $category = '', string $existingDescription = '', array $attributes = []): array
    {
        $system = <<<'PROMPT'
Você é um especialista em copywriting para e-commerce brasileiro, especializado em criar descrições para anúncios do Mercado Livre.

Regras:
- Escreva em português brasileiro, linguagem clara e profissional
- Destaque características, benefícios e diferenciais do produto
- Inclua informações técnicas relevantes
- Texto corrido, sem markdown, sem emojis excessivos
- Máximo 2000 caracteres
- Organize em parágrafos curtos e fáceis de ler
- Foque em converter o visitante em comprador
PROMPT;

        $user = "Crie uma descrição completa e atrativa para o seguinte anúncio:\n\nTítulo: {$title}";

        if ($category) {
            $user .= "\nCategoria: {$category}";
        }

        if (! empty($attributes)) {
            $attrText = collect($attributes)
                ->filter(fn ($v) => ! empty($v))
                ->map(fn ($v, $k) => "- {$k}: {$v}")
                ->implode("\n");
            if ($attrText) {
                $user .= "\n\nAtributos do produto:\n{$attrText}";
            }
        }

        if (! empty($existingDescription)) {
            $user .= "\n\nDescrição atual (melhore-a, mantendo informações relevantes):\n{$existingDescription}";
        }

        return ['system' => $system, 'user' => $user];
    }
}
