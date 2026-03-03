<?php

namespace App\Livewire\Marketplaces;

use App\Enums\AccountStatus;
use App\Enums\MarketplaceType;
use App\Models\Company;
use App\Models\MarketplaceAccount;
use Livewire\Component;

class MarketplaceForm extends Component
{
    public ?MarketplaceAccount $marketplace = null;

    // Account info
    public string $marketplace_type = 'mercado_livre';
    public string $account_name = '';
    public string $shop_id = '';
    public string $status = 'inactive';
    public ?int $company_id = null;

    // Credentials
    public string $client_id = '';
    public string $client_secret = '';
    public string $access_token = '';
    public string $refresh_token = '';
    public string $api_url = '';

    // Settings
    public bool $auto_sync_products = true;
    public bool $auto_sync_orders = true;
    public bool $auto_update_stock = true;
    public string $sync_interval = '30';

    public function mount(?MarketplaceAccount $marketplace = null): void
    {
        if ($marketplace && $marketplace->exists) {
            $this->marketplace = $marketplace;
            $this->marketplace_type = $marketplace->marketplace_type->value;
            $this->account_name = $marketplace->account_name;
            $this->shop_id = $marketplace->shop_id ?? '';
            $this->status = $marketplace->status->value;
            $this->company_id = $marketplace->company_id;

            $creds = $marketplace->credentials ?? [];
            $this->client_id = $creds['client_id'] ?? '';
            $this->client_secret = $creds['client_secret'] ?? '';
            $this->access_token = $creds['access_token'] ?? '';
            $this->refresh_token = $creds['refresh_token'] ?? '';
            $this->api_url = $creds['api_url'] ?? '';

            $settings = $marketplace->settings ?? [];
            $this->auto_sync_products = $settings['auto_sync_products'] ?? true;
            $this->auto_sync_orders = $settings['auto_sync_orders'] ?? true;
            $this->auto_update_stock = $settings['auto_update_stock'] ?? true;
            $this->sync_interval = (string) ($settings['sync_interval'] ?? '30');
        }

        if (! $this->company_id) {
            $this->company_id = Company::first()?->id;
        }
    }

    public function save(): mixed
    {
        $validated = $this->validate([
            'marketplace_type' => 'required|in:' . implode(',', array_column(MarketplaceType::cases(), 'value')),
            'account_name' => 'required|string|max:255',
            'shop_id' => 'nullable|string|max:100',
            'status' => 'required|in:' . implode(',', array_column(AccountStatus::cases(), 'value')),
            'company_id' => 'required|exists:companies,id',
            'client_id' => 'nullable|string|max:500',
            'client_secret' => 'nullable|string|max:500',
            'access_token' => 'nullable|string|max:2000',
            'refresh_token' => 'nullable|string|max:2000',
            'api_url' => 'nullable|url|max:500',
            'sync_interval' => 'required|integer|min:5|max:1440',
        ]);

        $credentials = array_filter([
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'access_token' => $this->access_token,
            'refresh_token' => $this->refresh_token,
            'api_url' => $this->api_url,
        ]);

        $settings = [
            'auto_sync_products' => $this->auto_sync_products,
            'auto_sync_orders' => $this->auto_sync_orders,
            'auto_update_stock' => $this->auto_update_stock,
            'sync_interval' => (int) $this->sync_interval,
        ];

        $data = [
            'company_id' => $this->company_id,
            'marketplace_type' => $this->marketplace_type,
            'account_name' => $this->account_name,
            'shop_id' => $this->shop_id ?: null,
            'status' => $this->status,
            'credentials' => ! empty($credentials) ? $credentials : null,
            'settings' => $settings,
        ];

        if ($this->marketplace) {
            $this->marketplace->update($data);
        } else {
            MarketplaceAccount::create($data);
        }

        session()->flash('success', $this->marketplace ? 'Conta atualizada.' : 'Conta de marketplace criada.');
        return $this->redirect(route('marketplaces.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.marketplaces.marketplace-form', [
            'companies' => Company::orderBy('name')->get(),
            'types' => MarketplaceType::cases(),
            'statuses' => AccountStatus::cases(),
        ]);
    }
}
