<?php

namespace App\Livewire\Invoices;

use App\Enums\NfeStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use Livewire\Component;

class InvoiceForm extends Component
{
    public ?Invoice $invoice = null;

    // Invoice data
    public string $type = 'nfe';
    public string $series = '1';
    public string $nature_operation = 'Venda de mercadoria';
    public ?int $order_id = null;
    public ?int $company_id = null;

    // Customer
    public string $customer_name = '';
    public string $customer_document = '';
    public string $customer_zipcode = '';
    public string $customer_street = '';
    public string $customer_number = '';
    public string $customer_complement = '';
    public string $customer_neighborhood = '';
    public string $customer_city = '';
    public string $customer_state = '';

    // Values
    public string $total_products = '0.00';
    public string $total_shipping = '0.00';
    public string $total_discount = '0.00';
    public string $total_tax = '0.00';

    // Order search
    public string $orderSearch = '';
    public array $orderResults = [];

    public function mount(?Invoice $invoice = null): void
    {
        if ($invoice && $invoice->exists) {
            $this->invoice = $invoice;
            $this->type = $invoice->type;
            $this->series = $invoice->series ?? '1';
            $this->nature_operation = $invoice->nature_operation;
            $this->order_id = $invoice->order_id;
            $this->company_id = $invoice->company_id;
            $this->customer_name = $invoice->customer_name;
            $this->customer_document = $invoice->customer_document ?? '';
            $this->total_products = (string) $invoice->total_products;
            $this->total_shipping = (string) $invoice->total_shipping;
            $this->total_discount = (string) $invoice->total_discount;
            $this->total_tax = (string) $invoice->total_tax;

            $addr = $invoice->customer_address ?? [];
            $this->customer_zipcode = $addr['zipcode'] ?? '';
            $this->customer_street = $addr['street'] ?? '';
            $this->customer_number = $addr['number'] ?? '';
            $this->customer_complement = $addr['complement'] ?? '';
            $this->customer_neighborhood = $addr['neighborhood'] ?? '';
            $this->customer_city = $addr['city'] ?? '';
            $this->customer_state = $addr['state'] ?? '';
        }

        if (! $this->company_id) {
            $this->company_id = Company::first()?->id;
        }
    }

    public function updatedOrderSearch(): void
    {
        if (strlen($this->orderSearch) < 2) {
            $this->orderResults = [];
            return;
        }

        $this->orderResults = Order::query()
            ->search($this->orderSearch)
            ->whereDoesntHave('invoices', fn ($q) => $q->where('status', NfeStatus::Approved))
            ->limit(10)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'customer_name' => $o->customer_name,
                'total' => (string) $o->total,
            ])
            ->toArray();
    }

    public function selectOrder(int $orderId): void
    {
        $order = Order::with('items')->find($orderId);
        if (! $order) {
            return;
        }

        $this->order_id = $order->id;
        $this->customer_name = $order->customer_name;
        $this->customer_document = $order->customer_document ?? '';
        $this->total_products = (string) $order->subtotal;
        $this->total_shipping = (string) $order->shipping_cost;
        $this->total_discount = (string) $order->discount;

        if ($order->shipping_address) {
            $addr = $order->shipping_address;
            $this->customer_zipcode = $addr['zipcode'] ?? '';
            $this->customer_street = $addr['street'] ?? '';
            $this->customer_number = $addr['number'] ?? '';
            $this->customer_complement = $addr['complement'] ?? '';
            $this->customer_neighborhood = $addr['neighborhood'] ?? '';
            $this->customer_city = $addr['city'] ?? '';
            $this->customer_state = $addr['state'] ?? '';
        }

        $this->orderSearch = '';
        $this->orderResults = [];
    }

    public function getTotalProperty(): float
    {
        return (float) $this->total_products + (float) $this->total_shipping
             - (float) $this->total_discount + (float) $this->total_tax;
    }

    public function save(): mixed
    {
        $validated = $this->validate([
            'type' => 'required|in:nfe,nfce',
            'series' => 'required|string|max:3',
            'nature_operation' => 'required|string|max:255',
            'customer_name' => 'required|string|max:255',
            'customer_document' => 'nullable|string|max:18',
            'total_products' => 'required|numeric|min:0',
            'total_shipping' => 'nullable|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'total_tax' => 'nullable|numeric|min:0',
        ]);

        $customerAddress = array_filter([
            'zipcode' => $this->customer_zipcode,
            'street' => $this->customer_street,
            'number' => $this->customer_number,
            'complement' => $this->customer_complement,
            'neighborhood' => $this->customer_neighborhood,
            'city' => $this->customer_city,
            'state' => $this->customer_state,
        ]);

        $data = [
            'order_id' => $this->order_id,
            'company_id' => $this->company_id,
            'type' => $this->type,
            'series' => $this->series,
            'nature_operation' => $this->nature_operation,
            'customer_name' => $this->customer_name,
            'customer_document' => $this->customer_document ?: null,
            'customer_address' => ! empty($customerAddress) ? $customerAddress : null,
            'total_products' => (float) $this->total_products,
            'total_shipping' => (float) $this->total_shipping,
            'total_discount' => (float) $this->total_discount,
            'total_tax' => (float) $this->total_tax,
            'total' => $this->total,
            'status' => NfeStatus::Pending,
        ];

        if ($this->invoice) {
            $this->invoice->update($data);
        } else {
            Invoice::create($data);
        }

        session()->flash('success', $this->invoice ? 'Nota fiscal atualizada.' : 'Nota fiscal criada.');
        return $this->redirect(route('invoices.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.invoices.invoice-form', [
            'companies' => Company::orderBy('name')->get(),
            'statuses' => NfeStatus::cases(),
        ]);
    }
}
