<?php

namespace App\Livewire\MelhorEnvios;

use App\Models\MelhorEnviosAccount;
use App\Services\MelhorEnviosService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class BalanceWidget extends Component
{
    /** @var 'compact'|'full' — compact for dashboard/expedition, full for settings */
    public string $mode = 'compact';

    /** Only show a specific account (for settings form) */
    public ?int $accountId = null;

    public array $balances = [];
    public bool $loading = true;

    // Add balance form
    public bool $showAddBalanceModal = false;
    public ?int $addBalanceAccountId = null;
    public string $addBalanceAccountName = '';
    public float $addBalanceValue = 50;
    public string $addBalanceMethod = 'pix';
    public ?string $paymentLink = null;

    public function mount(): void
    {
        $this->fetchBalances();
    }

    public function fetchBalances(): void
    {
        $this->loading = true;
        $this->balances = [];

        $query = MelhorEnviosAccount::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->whereNotNull('access_token');

        if ($this->accountId) {
            $query->where('id', $this->accountId);
        }

        $accounts = $query->get();

        foreach ($accounts as $account) {
            try {
                $service = new MelhorEnviosService($account);
                $data = $service->financialBalance();

                $this->balances[] = [
                    'id'          => $account->id,
                    'name'        => $account->name,
                    'environment' => $account->environment,
                    'balance'     => (float) ($data['balance'] ?? 0),
                ];
            } catch (\Throwable $e) {
                Log::warning("ME Balance fetch failed for account {$account->id}: " . $e->getMessage());
                $this->balances[] = [
                    'id'          => $account->id,
                    'name'        => $account->name,
                    'environment' => $account->environment,
                    'balance'     => null,
                    'error'       => 'Erro ao consultar saldo',
                ];
            }
        }

        $this->loading = false;
    }

    public function openAddBalance(int $accountId, string $accountName): void
    {
        $this->addBalanceAccountId = $accountId;
        $this->addBalanceAccountName = $accountName;
        $this->addBalanceValue = 50;
        $this->addBalanceMethod = 'pix';
        $this->paymentLink = null;
        $this->showAddBalanceModal = true;
    }

    public function submitAddBalance(): void
    {
        $this->validate([
            'addBalanceValue' => 'required|numeric|min:1|max:50000',
            'addBalanceMethod' => 'required|in:pix,boleto',
        ]);

        $account = MelhorEnviosAccount::where('company_id', Auth::user()->company_id)
            ->findOrFail($this->addBalanceAccountId);

        try {
            $service = new MelhorEnviosService($account);
            $result = $service->addBalance(
                $this->addBalanceValue,
                $this->addBalanceMethod,
                route('settings.me.index')
            );

            $this->paymentLink = $result['link'] ?? $result['url'] ?? $result['payment_url'] ?? null;

            if ($this->paymentLink) {
                $this->dispatch('open-url', url: $this->paymentLink);
            }

            $this->dispatch('notify', type: 'success', message: 'Solicitação de saldo enviada! Realize o pagamento para creditar.');
        } catch (\Throwable $e) {
            Log::error("ME addBalance failed: " . $e->getMessage());
            $this->dispatch('notify', type: 'error', message: 'Erro ao solicitar saldo: ' . $e->getMessage());
        }
    }

    public function closeAddBalance(): void
    {
        $this->showAddBalanceModal = false;
        $this->paymentLink = null;
        $this->fetchBalances();
    }

    public function render()
    {
        return view('livewire.melhor-envios.balance-widget');
    }
}
