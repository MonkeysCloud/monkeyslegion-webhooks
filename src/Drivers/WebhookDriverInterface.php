<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks\Drivers;

use MonkeysLegion\Webhooks\Webhook;
use MonkeysLegion\Webhooks\WebhookDelivery;

/**
 * MonkeysLegion Framework — Webhooks Package
 *
 * Contract for webhook storage drivers.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
interface WebhookDriverInterface
{
    /**
     * Register a webhook.
     */
    public function register(Webhook $webhook): void;

    /**
     * Unregister a webhook by ID.
     */
    public function unregister(string $id): void;

    /**
     * Get a webhook by ID.
     */
    public function find(string $id): ?Webhook;

    /**
     * Get all webhooks that listen for a given event.
     *
     * @return list<Webhook>
     */
    public function findByEvent(string $event): array;

    /**
     * Get all registered webhooks.
     *
     * @return list<Webhook>
     */
    public function all(): array;

    /**
     * Record a delivery attempt.
     */
    public function recordDelivery(WebhookDelivery $delivery): void;

    /**
     * Get delivery history for a webhook.
     *
     * @param string $webhookId
     * @param int $limit
     * @return list<WebhookDelivery>
     */
    public function deliveries(string $webhookId, int $limit = 50): array;
}
