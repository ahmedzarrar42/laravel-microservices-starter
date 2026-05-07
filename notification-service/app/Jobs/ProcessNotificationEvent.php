<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessNotificationEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(private array $payload) {}

    public function handle(): void
    {
        $event = $this->payload['event'] ?? null;

        Log::info('Notification service processing event', ['event' => $event]);

        match ($event) {
            'user.registered'      => $this->sendWelcomeEmail(),
            'order.created'        => $this->sendOrderConfirmation(),
            'order.status_changed' => $this->sendOrderStatusUpdate(),
            default                => Log::warning("Unknown event: {$event}"),
        };
    }

    private function sendWelcomeEmail(): void
    {
        $email = $this->payload['email'] ?? null;
        $name  = $this->payload['name'] ?? 'User';

        if (! $email) return;

        Mail::raw(
            "Hi {$name},\n\nWelcome! Your account has been created successfully.\n\nBest regards,\nThe Team",
            function (Message $message) use ($email, $name) {
                $message->to($email, $name)
                        ->subject('Welcome to our platform!');
            }
        );

        Log::info('Welcome email sent', ['email' => $email]);
    }

    private function sendOrderConfirmation(): void
    {
        $email   = $this->payload['user_email'] ?? null;
        $name    = $this->payload['user_name'] ?? 'Customer';
        $orderId = $this->payload['order_id'] ?? null;
        $total   = $this->payload['total_amount'] ?? 0;

        if (! $email) return;

        Mail::raw(
            "Hi {$name},\n\nYour order #{$orderId} has been placed successfully.\nTotal: €{$total}\n\nWe'll update you when it ships.",
            function (Message $message) use ($email, $name, $orderId) {
                $message->to($email, $name)
                        ->subject("Order #{$orderId} Confirmed");
            }
        );

        Log::info('Order confirmation sent', ['order_id' => $orderId, 'email' => $email]);
    }

    private function sendOrderStatusUpdate(): void
    {
        $orderId   = $this->payload['order_id'] ?? null;
        $newStatus = $this->payload['new_status'] ?? null;
        $userId    = $this->payload['user_id'] ?? null;

        // In production: fetch user email via User Service REST call
        Log::info('Order status notification', [
            'order_id'   => $orderId,
            'new_status' => $newStatus,
            'user_id'    => $userId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job failed', [
            'payload' => $this->payload,
            'error'   => $exception->getMessage(),
        ]);
    }
}
