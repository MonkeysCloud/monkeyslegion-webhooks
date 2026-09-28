<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks\Drivers;

use MonkeysLegion\Webhooks\Webhook;
use MonkeysLegion\Webhooks\WebhookDelivery;

/**
 * MonKeysLegion Framework — Webhooks Package
 *
 * In-memory driver for testing and development.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class InMemoryDriver implements WebhookDriverInterface
{
    /** @var array<string, Webhook> */
    private array $webhooks = [];

    /** @var list<WebhookDelivery> */
    private array $deliveries = [];

    public function register(Webhook $webhook): void
    {
        $this->webhooks[$webhook->id] = $webhook;
    }

    public function unregister(string $id): void
    {
        unset($this->webhooks[$id]);
    }

    public function find(string $id): ?Webhook
    {
        return $this->webhooks[$id] ?? null;
    }

    public function findByEvent(string $event): array
    {
        return array_values(array_filter(
            $this->webhooks,
            fn(Webhook $w) => $w->active && $w->listensFor($event),
        ));
    }

    public function all(): array
    {
        return array_values($this->webhooks);
    }

    public function recordDelivery(WebhookDelivery $delivery): void
    {
        $this->deliveries[] = $delivery;
    }

    public function deliveries(string $webhookId, int $limit = 50): array
    {
        $filtered = array_filter(
            $this->deliveries,
            fn(WebhookDelivery $d) => $d->webhookId === $webhookId,
        );
        return array_slice(array_reverse($filtered), 0, $limit);
    }
}
