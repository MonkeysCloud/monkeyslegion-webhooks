<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks;

use MonkeysLegion\Webhooks\Drivers\WebhookDriverInterface;
use MonkeysLegion\Webhooks\WebhookDelivery;

/**
 * MonKeysLegion Framework — Webhooks Package
 *
 * Central manager for dispatching and tracking webhooks.
 *
 * Usage:
 *   $manager->register(new Webhook('wh-1', 'https://example.com/hook', ['order.created']));
 *   $manager->dispatch('order.created', ['order_id' => 42, 'total' => 99.99]);
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class WebhookManager
{
    public function __construct(
        private readonly WebhookDriverInterface $driver,
        private readonly WebhookSigner $signer,
        private readonly int $timeout = 30,
    ) {}

    /**
     * Register a webhook endpoint.
     */
    public function register(Webhook $webhook): void
    {
        $this->driver->register($webhook);
    }

    /**
     * Unregister a webhook endpoint.
     */
    public function unregister(string $id): void
    {
        $this->driver->unregister($id);
    }

    /**
     * Get a webhook by ID.
     */
    public function find(string $id): ?Webhook
    {
        return $this->driver->find($id);
    }

    /**
     * Get all registered webhooks.
     *
     * @return list<Webhook>
     */
    public function all(): array
    {
        return $this->driver->all();
    }

    /**
     * Dispatch an event to all matching webhooks.
     *
     * @param string               $event   Event name.
     * @param array<string, mixed> $payload Event payload.
     *
     * @return list<WebhookDelivery> Delivery records.
     */
    public function dispatch(string $event, array $payload): array
    {
        $webhooks = $this->driver->findByEvent($event);
        $deliveries = [];

        foreach ($webhooks as $webhook) {
            $delivery = $this->deliver($webhook, $event, $payload);
            $this->driver->recordDelivery($delivery);
            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    /**
     * Get delivery history for a webhook.
     *
     * @return list<WebhookDelivery>
     */
    public function deliveries(string $webhookId, int $limit = 50): array
    {
        return $this->driver->deliveries($webhookId, $limit);
    }

    /**
     * Deliver a single webhook with retry logic.
     */
    private function deliver(Webhook $webhook, string $event, array $payload): WebhookDelivery
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $deliveryId = uniqid('wh_del_', true);

        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $event,
            'X-Webhook-Id: ' . $deliveryId,
        ];

        // Sign if secret is configured
        if ($webhook->secret) {
            $signer = new WebhookSigner($webhook->secret);
            $headers[] = 'X-Webhook-Signature: ' . $signer->sign($body);
        }

        // Add custom headers
        foreach ($webhook->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        $attempt = 0;
        $maxRetries = $webhook->maxRetries;

        while ($attempt < $maxRetries) {
            $attempt++;

            $ch = curl_init($webhook->url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                return new WebhookDelivery(
                    id: $deliveryId,
                    webhookId: $webhook->id,
                    event: $event,
                    payload: $payload,
                    status: WebhookDelivery::STATUS_SUCCESS,
                    statusCode: $httpCode,
                    response: (string) $response,
                    attempt: $attempt,
                    deliveredAt: new \DateTimeImmutable(),
                );
            }

            $errorMsg = $response !== false
                ? "HTTP {$httpCode}: {$response}"
                : "cURL error: {$error}";

            // Retry with exponential backoff
            if ($attempt < $maxRetries) {
                usleep(($attempt ** 2) * 1_000_000);
            }

            $lastError = $errorMsg;
        }

        return new WebhookDelivery(
            id: $deliveryId,
            webhookId: $webhook->id,
            event: $event,
            payload: $payload,
            status: WebhookDelivery::STATUS_FAILED,
            statusCode: $httpCode ?? null,
            response: $response !== false ? (string) $response : null,
            attempt: $attempt,
            deliveredAt: new \DateTimeImmutable(),
            error: $lastError ?? 'Unknown error',
        );
    }
}
