<?php

namespace App\Livewire\Marketplaces;

use App\Enums\AccountStatus;
use App\Enums\MarketplaceType;
use App\Models\Company;
use App\Models\MarketplaceAccount;
use App\Models\SystemSetting;
use Livewire\Component;

class MarketplaceForm extends Component
{
    // Store only the ID to avoid Livewire serializing the model with
    // encrypted:json attributes (credentials), which breaks hydration.
    public ?int $marketplaceId = null;
    public bool $isOAuth       = false;

    // Account info
    public string $marketplace_type = 'mercado_livre';
    public string $account_name     = '';
    public string $shop_id          = '';
    public string $status           = 'inactive';
    public ?int   $company_id       = null;

    // Credentials (editable only for non-OAuth types)
    public string $client_id     = '';
    public string $client_secret = '';
    public string $access_token  = '';
    public string $refresh_token = '';
    public string $api_url       = '';

    // Settings
    public bool   $auto_sync_products = true;
    public bool   $auto_sync_orders   = true;
    public bool   $auto_update_stock  = true;
    public string $sync_interval      = '30';

    public function mount(?int $marketplaceId = null): void
    {
        if ($marketplaceId) {
            $this->marketplaceId = $marketplaceId;
            $this->loadFromModel(MarketplaceAccount::findOrFail($marketplaceId));
        }

        if (! $this->company_id) {
            $this->company_id = Company::first()?->id;
        }
    }

    private function loadFromModel(MarketplaceAccount $marketplace): void
    {
        $this->marketplace_type = $marketplace->marketplace_type->value;
        $this->isOAuth          = $marketplace->marketplace_type->supportsOAuth();
        $this->account_name     = $marketplace->account_name;
        $this->shop_id          = $marketplace->shop_id ?? '';
        $this->status           = $marketplace->status->value;
        $this->company_id       = $marketplace->company_id;

        $type = $marketplace->marketplace_type->value;

        try {
            $creds = $marketplace->credentials ?? [];
        } catch (\Exception $e) {
            $creds = [];
        }

        $this->client_id = $creds['client_id']
            ?? SystemSetting::get('marketplaces', "{$type}_client_id")
            ?? '';

        $hasSecret = ! empty($creds['client_secret'])
            || ! empty(SystemSetting::get('marketplaces', "{$type}_client_secret"));
        $this->client_secret = $creds['client_secret']
            ?? ($hasSecret ? '••••••••' : '');

        $this->access_token  = $creds['access_token'] ?? '';
        $this->refresh_token = $creds['refresh_token'] ?? '';
        $this->api_url       = $creds['api_url'] ?? '';

        $settings = $marketplace->settings ?? [];

        $this->auto_sync_products = $settings['auto_sync_products'] ?? true;
        $this->auto_sync_orders   = $settings['auto_sync_orders'] ?? true;
        $this->auto_update_stock  = $settings['auto_update_stock'] ?? true;
        $this->sync_interval      = (string) ($settings['sync_interval'] ?? '30');
    }

    public function updatedMarketplaceType(string $value): void
    {
        try {
            $this->isOAuth = MarketplaceType::from($value)->supportsOAuth();
        } catch (\ValueError $e) {
            $this->isOAuth = false;
        }
    }

    public function save(): mixed
    {
        $validated = $this->validate([
            'marketplace_type' => 'required|in:' . implode(',', array_column(MarketplaceType::cases(), 'value')),
            'account_name'     => 'required|string|max:255',
            'shop_id'          => 'nullable|string|max:100',
            'status'           => 'required|in:' . implode(',', array_column(AccountStatus::cases(), 'value')),
            'company_id'       => 'required|exists:companies,id',
            'client_id'        => 'nullable|string|max:500',
            'client_secret'    => 'nullable|string|max:500',
            'access_token'     => 'nullable|string|max:2000',
            'refresh_token'    => 'nullable|string|max:2000',
            'api_url'          => 'nullable|url|max:500',
            'sync_interval'    => 'required|integer|min:5|max:1440',
        ]);

        // Resolve client_secret placeholder back to real value
        $clientSecret = $this->client_secret;
        if ($clientSecret === '••••••••') {
            $type = $this->marketplace_type;
            $existing = $this->marketplaceId ? MarketplaceAccount::find($this->marketplaceId) : null;
            try {
                $existingCreds = $existing?->credentials ?? [];
            } catch (\Exception $e) {
                $existingCreds = [];
            }
            $clientSecret = $existingCreds['client_secret']
                ?? SystemSetting::get('marketplaces', "{$type}_client_secret")
                ?? '';
        }

        $credentials = array_filter([
            'client_id'     => $this->client_id,
            'client_secret' => $clientSecret,
            'access_token'  => $this->access_token,
            'refresh_token' => $this->refresh_token,
            'api_url'       => $this->api_url,
        ]);

        $settings = [
            'auto_sync_products' => $this->auto_sync_products,
            'auto_sync_orders'   => $this->auto_sync_orders,
            'auto_update_stock'  => $this->auto_update_stock,
            'sync_interval'      => (int) $this->sync_interval,
        ];

        $data = [
            'company_id'       => $this->company_id,
            'marketplace_type' => $this->marketplace_type,
            'account_name'     => $this->account_name,
            'shop_id'          => $this->shop_id ?: null,
            'status'           => $this->status,
            'credentials'      => ! empty($credentials) ? $credentials : null,
            'settings'         => $settings,
        ];

        $account = $this->marketplaceId ? MarketplaceAccount::find($this->marketplaceId) : null;

        if ($account) {
            $account->update($data);
        } else {
            MarketplaceAccount::create($data);
        }

        session()->flash('success', $account ? 'Conta atualizada.' : 'Conta de marketplace criada.');
        return $this->redirect(route('marketplaces.index'), navigate: false);
    }

    public function render()
    {
        // Reload model from DB on every render (never store Eloquent model with
        // encrypted attributes as a public Livewire property).
        $marketplace = $this->marketplaceId
            ? MarketplaceAccount::find($this->marketplaceId)
            : null;

        return view('livewire.marketplaces.marketplace-form', [
            'companies'   => Company::orderBy('name')->get(),
            'types'       => MarketplaceType::cases(),
            'statuses'    => AccountStatus::cases(),
            'marketplace' => $marketplace,
        ]);
    }
}
