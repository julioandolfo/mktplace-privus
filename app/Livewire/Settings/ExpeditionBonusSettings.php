<?php

namespace App\Livewire\Settings;

use App\Models\ExpeditionBonusConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ExpeditionBonusSettings extends Component
{
    public int    $points_value_cents    = 10;
    public int    $default_product_points = 1;
    public int    $deadline_buffer_days  = 1;
    public bool   $is_active             = true;

    public function mount(): void
    {
        $config = ExpeditionBonusConfig::forCompany(Auth::user()->company_id);

        $this->points_value_cents    = $config->points_value_cents;
        $this->default_product_points = $config->default_product_points;
        $this->deadline_buffer_days  = $config->deadline_buffer_days;
        $this->is_active             = $config->is_active;
    }

    public function save(): void
    {
        $this->validate([
            'points_value_cents'    => 'required|integer|min:1',
            'default_product_points' => 'required|integer|min:1',
            'deadline_buffer_days'  => 'required|integer|min:0|max:10',
        ]);

        ExpeditionBonusConfig::updateOrCreate(
            ['company_id' => Auth::user()->company_id],
            [
                'points_value_cents'    => $this->points_value_cents,
                'default_product_points' => $this->default_product_points,
                'deadline_buffer_days'  => $this->deadline_buffer_days,
                'is_active'             => $this->is_active,
            ]
        );

        session()->flash('success', 'Configurações de bonificação salvas.');
    }

    public function render()
    {
        $pointValueFormatted = 'R$ ' . number_format($this->points_value_cents / 100, 2, ',', '.');

        return view('livewire.settings.expedition-bonus-settings', [
            'pointValueFormatted' => $pointValueFormatted,
        ]);
    }
}
