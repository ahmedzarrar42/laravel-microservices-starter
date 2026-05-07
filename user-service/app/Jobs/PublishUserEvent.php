<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishUserEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(private array $payload) {}

    public function handle(): void
    {
        // This job is dispatched to the 'user-events' RabbitMQ exchange
        // The Notification Service consumes from this exchange
        Log::info('User event published to RabbitMQ', [
            'event'   => $this->payload['event'],
            'user_id' => $this->payload['user_id'] ?? null,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to publish user event', [
            'payload' => $this->payload,
            'error'   => $exception->getMessage(),
        ]);
    }
}
