<?php

namespace App\Livewire\Settings;

use App\Models\ExpeditionOperator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ExpeditionOperators extends Component
{
    public array $operators = [];

    // Form
    public bool   $showForm    = false;
    public ?int   $editingId   = null;
    public string $formName    = '';
    public string $formRole    = '';
    public bool   $formDefault = false;
    public int    $formSort    = 0;

    public function mount(): void
    {
        $this->loadOperators();
    }

    protected function loadOperators(): void
    {
        $this->operators = ExpeditionOperator::where('company_id', Auth::user()->company_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function openForm(?int $id = null): void
    {
        $this->resetErrorBag();

        if ($id) {
            $op = ExpeditionOperator::where('company_id', Auth::user()->company_id)->findOrFail($id);
            $this->editingId   = $op->id;
            $this->formName    = $op->name;
            $this->formRole    = $op->role ?? '';
            $this->formDefault = $op->is_default;
            $this->formSort    = $op->sort_order;
        } else {
            $this->editingId   = null;
            $this->formName    = '';
            $this->formRole    = '';
            $this->formDefault = false;
            $this->formSort    = count($this->operators);
        }

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'formName' => 'required|string|max:100',
            'formRole' => 'nullable|string|max:60',
            'formSort' => 'integer|min:0',
        ]);

        $companyId = Auth::user()->company_id;

        // Se marcou como padrão, remove padrão dos outros
        if ($this->formDefault) {
            ExpeditionOperator::where('company_id', $companyId)
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->update(['is_default' => false]);
        }

        ExpeditionOperator::updateOrCreate(
            ['id' => $this->editingId, 'company_id' => $companyId],
            [
                'company_id' => $companyId,
                'name'       => $this->formName,
                'role'       => $this->formRole ?: null,
                'is_default' => $this->formDefault,
                'sort_order' => $this->formSort,
            ]
        );

        $this->showForm = false;
        $this->loadOperators();
        session()->flash('success', $this->editingId ? 'Operador atualizado.' : 'Operador criado.');
    }

    public function toggleActive(int $id): void
    {
        $op = ExpeditionOperator::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $op->update(['is_active' => ! $op->is_active]);
        $this->loadOperators();
    }

    public function setDefault(int $id): void
    {
        $companyId = Auth::user()->company_id;

        ExpeditionOperator::where('company_id', $companyId)->update(['is_default' => false]);
        ExpeditionOperator::where('company_id', $companyId)->where('id', $id)->update(['is_default' => true]);

        $this->loadOperators();
    }

    public function moveUp(int $id): void
    {
        $this->reorder($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->reorder($id, 1);
    }

    protected function reorder(int $id, int $direction): void
    {
        $companyId = Auth::user()->company_id;
        $all = ExpeditionOperator::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $index = $all->search(fn ($o) => $o->id === $id);
        if ($index === false) return;

        $swapIndex = $index + $direction;
        if ($swapIndex < 0 || $swapIndex >= $all->count()) return;

        $current = $all[$index];
        $swap    = $all[$swapIndex];

        $tmpSort = $current->sort_order;
        $current->update(['sort_order' => $swap->sort_order]);
        $swap->update(['sort_order' => $tmpSort]);

        // Garante que não ficam iguais
        if ($current->sort_order === $swap->sort_order) {
            $current->update(['sort_order' => $swap->sort_order + $direction]);
        }

        $this->loadOperators();
    }

    public function delete(int $id): void
    {
        ExpeditionOperator::where('company_id', Auth::user()->company_id)
            ->where('id', $id)
            ->delete();

        $this->loadOperators();
        session()->flash('success', 'Operador removido.');
    }

    public function render()
    {
        return view('livewire.settings.expedition-operators');
    }
}
