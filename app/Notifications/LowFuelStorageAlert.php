<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\FuelStorage;

/**
 * Command untuk membuat notification ini:
 * php artisan make:notification LowFuelStorageAlert
 * 
 * Notification untuk alert ketika fuel storage di bawah threshold
 */
class LowFuelStorageAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected $fuelStorage;
    protected $alertType;

    /**
     * Create a new notification instance.
     */
    public function __construct(FuelStorage $fuelStorage, string $alertType = 'low')
    {
        $this->fuelStorage = $fuelStorage;
        $this->alertType = $alertType; // 'low' or 'critical'
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->alertType === 'critical' 
            ? '🚨 CRITICAL: Fuel Storage Critically Low' 
            : '⚠️ WARNING: Fuel Storage Below Threshold';

        $greeting = $this->alertType === 'critical' 
            ? 'CRITICAL ALERT' 
            : 'Low Fuel Warning';

        $message = new MailMessage();
        $message->subject($subject)
                ->greeting($greeting)
                ->line("Fuel storage **{$this->fuelStorage->name}** ({$this->fuelStorage->code}) is running low.")
                ->line("**Current Status:**")
                ->line("- Location: {$this->fuelStorage->location}")
                ->line("- Current Capacity: " . number_format($this->fuelStorage->current_capacity, 0) . " liters")
                ->line("- Maximum Capacity: " . number_format($this->fuelStorage->max_capacity, 0) . " liters")
                ->line("- Current Level: " . round($this->fuelStorage->capacity_percentage, 1) . "%")
                ->line("- Minimum Threshold: " . number_format($this->fuelStorage->min_threshold, 0) . " liters");

        if ($this->alertType === 'critical') {
            $message->line("")
                    ->line("⚠️ **IMMEDIATE ACTION REQUIRED**")
                    ->line("This storage is critically low and may affect mining operations.");
        } else {
            $message->line("")
                    ->line("Please arrange for fuel replenishment to avoid operational disruption.");
        }

        $message->action('View Fuel Monitor Dashboard', url('/admin'))
                ->line('Thank you for maintaining operational efficiency!');

        return $message;
    }

    /**
     * Get the array representation of the notification for database.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'fuel_storage_alert',
            'alert_type' => $this->alertType,
            'storage_id' => $this->fuelStorage->id,
            'storage_name' => $this->fuelStorage->name,
            'storage_code' => $this->fuelStorage->code,
            'location' => $this->fuelStorage->location,
            'current_capacity' => $this->fuelStorage->current_capacity,
            'max_capacity' => $this->fuelStorage->max_capacity,
            'capacity_percentage' => $this->fuelStorage->capacity_percentage,
            'min_threshold' => $this->fuelStorage->min_threshold,
            'message' => $this->getAlertMessage(),
            'priority' => $this->alertType === 'critical' ? 'high' : 'medium',
        ];
    }

    /**
     * Get alert message
     */
    private function getAlertMessage(): string
    {
        $percentage = round($this->fuelStorage->capacity_percentage, 1);
        
        if ($this->alertType === 'critical') {
            return "Storage {$this->fuelStorage->name} is critically low at {$percentage}%. Immediate refueling required.";
        }
        
        return "Storage {$this->fuelStorage->name} is below threshold at {$percentage}%. Please arrange refueling.";
    }
}