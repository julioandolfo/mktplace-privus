<?php

namespace App\Livewire\Purchases;

use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SuppliersList extends Component
{
    public array  $suppliers = [];
    public bool   $showForm  = false;
    public ?int   $editingId = null;

    // Form fields
    public string $formName        = '';
    public string $formContactName = '';
    public string $formEmail       = '';
    public string $formPhone       = '';
    public string $formWhatsapp    = '';
    public string $formWebsite     = '';
    public string $formNotes       = '';

    public function mount(): void
    {
        $this->loadSuppliers();
    }

    protected function loadSuppliers(): void
    {
        $this->suppliers = Supplier::where('company_id', Auth::user()->company_id)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function openForm(?int $id = null): void
    {
        $this->resetErrorBag();

        if ($id) {
            $s = Supplier::where('company_id', Auth::user()->company_id)->findOrFail($id);
            $this->editingId      = $s->id;
            $this->formName       = $s->name;
            $this->formContactName = $s->contact_name ?? '';
            $this->formEmail      = $s->email ?? '';
            $this->formPhone      = $s->phone ?? '';
            $this->formWhatsapp   = $s->whatsapp ?? '';
            $this->formWebsite    = $s->website ?? '';
            $this->formNotes      = $s->notes ?? '';
        } else {
            $this->editingId      = null;
            $this->formName       = '';
            $this->formContactName = '';
            $this->formEmail      = '';
            $this->formPhone      = '';
            $this->formWhatsapp   = '';
            $this->formWebsite    = '';
            $this->formNotes      = '';
        }

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'formName'  => 'required|string|max:150',
            'formEmail' => 'nullable|email|max:150',
        ]);

        Supplier::updateOrCreate(
            ['id' => $this->editingId, 'company_id' => Auth::user()->company_id],
            [
                'company_id'   => Auth::user()->company_id,
                'name'         => $this->formName,
                'contact_name' => $this->formContactName ?: null,
                'email'        => $this->formEmail ?: null,
                'phone'        => $this->formPhone ?: null,
                'whatsapp'     => $this->formWhatsapp ?: null,
                'website'      => $this->formWebsite ?: null,
                'notes'        => $this->formNotes ?: null,
            ]
        );

        $this->showForm = false;
        $this->loadSuppliers();
        session()->flash('success', $this->editingId ? 'Fornecedor atualizado.' : 'Fornecedor criado.');
    }

    public function toggleActive(int $id): void
    {
        $s = Supplier::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
        $this->loadSuppliers();
    }

    public function delete(int $id): void
    {
        Supplier::where('company_id', Auth::user()->company_id)->where('id', $id)->delete();
        $this->loadSuppliers();
        session()->flash('success', 'Fornecedor removido.');
    }

    public function render()
    {
        return view('livewire.purchases.suppliers-list');
    }
}
