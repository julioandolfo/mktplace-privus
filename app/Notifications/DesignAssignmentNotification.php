<?php

namespace App\Notifications;

use App\Models\DesignAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DesignAssignmentNotification extends Notification
{
    use Queueable;

    public function __construct(public DesignAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->assignment->order;
        return [
            'type'          => 'design_assignment',
            'assignment_id' => $this->assignment->id,
            'order_id'      => $order?->id,
            'order_number'  => $order?->order_number,
            'customer_name' => $order?->customer_name,
            'message'       => "Novo pedido #{$order?->order_number} atribuído para design.",
            'url'           => route('designer.edit', $this->assignment),
        ];
    }
}
