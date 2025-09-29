<?php

namespace App\Console\Commands;

use App\Models\Financing_plan;
use App\Notifications\FcmPushNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-payment-reminders';

    // inject FcmService
    protected $fcmService;

    // public function __construct(FcmNotificationService $fcmService)
    // {
    //     parent::__construct();
    //     $this->fcmService = $fcmService;
    // }


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie des notifications de rappel aux appareils dont le paiement arrive à échéance.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Début de l\'envoi des rappels de paiement...');

        // --- Rappel à J-5 ---
        $dateJ5 = Carbon::today()->addDays(5)->toDateString();
        $this->sendRemindersForDate($dateJ5, "Votre paiement arrive à échéance dans 5 jours.");

        // --- Rappel à J-1 (la veille) ---
        $dateJ1 = Carbon::today()->addDay()->toDateString();
        $this->sendRemindersForDate($dateJ1, "Votre paiement expire demain. Pensez à recharger.");

        $this->info('Envoi des rappels terminé.');
        return 0;
    }

    /**
     * Fonction d'aide pour trouver les plans et envoyer les notifications pour une date donnée.
     */
    private function sendRemindersForDate(string $date, string $messageBody)
    {
        // On recherche tous les plans (actifs) dont la date d'échéance correspond
        $plans = Financing_plan::where('status', 'active')
            ->whereDate('next_payment_due_date', '=', $date)
            ->with('device') // Charger l'appareil lié pour obtenir le token FCM
            ->get();

        if ($plans->isEmpty()) {
            $this->line("Aucun rappel à envoyer pour le " . $date . ".");
            return;
        }

        $this->line("Envoi de " . $plans->count() . " rappels pour le " . $date . "...");

        foreach ($plans as $plan) {
            // S'assurer que l'appareil existe et a un token FCM enregistré
            if ($plan->device && $plan->device->fcm_token) {

                $title = "Rappel de Paiement";

                try {
                    // Appeler le service pour envoyer le push
                    $plan->device->notify(new FcmPushNotification(
                        title: $title,
                        body: $messageBody,
                        image: null,
                        data: []
                    ));
                } catch (\Exception $e) {
                    // Ne pas bloquer la boucle, juste enregistrer l'erreur
                    $this->error("Échec d'envoi au device " . $plan->device->id . ": " . $e->getMessage());
                }
            }
        }
    }
}
