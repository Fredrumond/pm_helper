<?php

namespace App\Jobs;

use App\Models\CardDiscovery;
use App\Services\CardDiscoveryService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AdvanceCardDiscovery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1000;

    public int $timeout = 60;

    public function __construct(public int $cardDiscoveryId) {}

    public function retryUntil(): DateTimeInterface
    {
        $discovery = CardDiscovery::query()->find($this->cardDiscoveryId);

        if ($discovery?->deadline_at === null) {
            return now()->addMinutes(5);
        }

        return $discovery->deadline_at->copy()->addMinutes(2);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('card-discovery-'.$this->cardDiscoveryId))
                ->releaseAfter(max(3, (int) config('services.cursor.poll_seconds')))
                ->expireAfter(120),
        ];
    }

    public function handle(CardDiscoveryService $service): void
    {
        $discovery = CardDiscovery::query()->find($this->cardDiscoveryId);

        if ($discovery === null || $discovery->isTerminal()) {
            return;
        }

        $pending = $service->advance($discovery);

        if ($pending && config('queue.default') !== 'sync') {
            $this->release(max(3, (int) config('services.cursor.poll_seconds')));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $discovery = CardDiscovery::query()->find($this->cardDiscoveryId);

        if ($discovery === null) {
            return;
        }

        app(CardDiscoveryService::class)->markTimedOut($discovery);
    }
}
