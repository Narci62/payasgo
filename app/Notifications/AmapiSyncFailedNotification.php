<?php

namespace App\Notifications;

use App\Models\Device;
use App\Models\Financing_plan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AmapiSyncFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Financing_plan $plan,
        public Device $device,
        public string $action,
        public string $error
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clientName = $this->device->client?->full_name ?? 'N/A';
        $deviceImei = $this->device->imei ?? 'N/A';
        $phoneModel = $this->device->phone?->model ?? 'N/A';

        return (new MailMessage)
            ->subject('Échec synchro AMAPI - Action requise')
            ->line("L'action **{$this->action}** a échoué sur le device du client **{$clientName}**.")
            ->line('**Détails du device :**')
            ->line("- IMEI : {$deviceImei}")
            ->line("- Modèle : {$phoneModel}")
            ->line("- ID Device : {$this->device->id}")
            ->line('**Détails du plan :**')
            ->line("- ID Plan : {$this->device->financingPlan?->id}")
            ->line("- Solde restant : {$this->plan->remaining_balance} FCFA")
            ->line("**Erreur :** {$this->error}")
            ->line("**Action requise :** Veuillez effectuer l'action manuellement depuis le panneau admin.")
            ->action('Voir le device', url('/admin/devices/'.$this->device->id))
            ->line('Merci de ne pas répondre à cet email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'financing_plan_id' => $this->plan->id,
            'device_id' => $this->device->id,
            'action' => $this->action,
            'error' => $this->error,
            'client_name' => $this->device->client?->full_name,
            'message' => "Échec synchro AMAPI ({$this->action}) pour le device du client {$this->device->client?->full_name}",
        ];
    }
}
