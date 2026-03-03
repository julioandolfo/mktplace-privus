<?php

namespace App\Livewire\Invoices;

use App\Enums\NfeStatus;
use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceList extends Component
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

    public function deleteInvoice(int $id): void
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status === NfeStatus::Approved) {
            session()->flash('error', 'Notas aprovadas nao podem ser removidas. Cancele primeiro.');
            return;
        }

        $invoice->delete();
        session()->flash('success', 'Nota fiscal removida com sucesso.');
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with(['order', 'company'])
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.invoices.invoice-list', [
            'invoices' => $invoices,
            'statuses' => NfeStatus::cases(),
        ]);
    }
}
