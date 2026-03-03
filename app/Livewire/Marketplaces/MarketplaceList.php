<?php

namespace App\Livewire\Marketplaces;

use App\Enums\AccountStatus;
use App\Enums\MarketplaceType;
use App\Models\MarketplaceAccount;
use Livewire\Component;
use Livewire\WithPagination;

class MarketplaceList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $type = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'type' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleStatus(int $id): void
    {
        $account = MarketplaceAccount::findOrFail($id);

        if ($account->status === AccountStatus::Active) {
            $account->update(['status' => AccountStatus::Inactive]);
            session()->flash('success', "Conta {$account->account_name} desativada.");
        } else {
            $account->update(['status' => AccountStatus::Active]);
            session()->flash('success', "Conta {$account->account_name} ativada.");
        }
    }

    public function deleteAccount(int $id): void
    {
        $account = MarketplaceAccount::findOrFail($id);
        $account->delete();
        session()->flash('success', "Conta {$account->account_name} removida.");
    }

    public function render()
    {
        $accounts = MarketplaceAccount::query()
            ->with('company')
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->type, fn ($q) => $q->where('marketplace_type', $this->type))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.marketplaces.marketplace-list', [
            'accounts' => $accounts,
            'statuses' => AccountStatus::cases(),
            'types' => MarketplaceType::cases(),
            'counts' => [
                'total' => MarketplaceAccount::count(),
                'active' => MarketplaceAccount::where('status', AccountStatus::Active)->count(),
                'error' => MarketplaceAccount::where('status', AccountStatus::Error)->count(),
            ],
        ]);
    }
}
