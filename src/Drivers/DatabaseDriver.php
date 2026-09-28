<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks\Drivers;

use MonkeysLegion\Webhooks\Webhook;
use MonkeysLegion\Webhooks\WebhookDelivery;
use PDO;
use PDOException;

/**
 * MonKeysLegion Framework — Webhooks Package
 *
 * Database driver for webhook storage.
 *
 * Schema:
 *   CREATE TABLE webhooks (
 *     id VARCHAR(255) PRIMARY KEY,
 *     url TEXT NOT NULL,
 *     events JSON,
 *     secret VARCHAR(255),
 *     active BOOLEAN DEFAULT TRUE,
 *     headers JSON,
 *     max_retries INT DEFAULT 3,
 *     created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 *   );
 *
 *   CREATE TABLE webhook_deliveries (
 *     id VARCHAR(255) PRIMARY KEY,
 *     webhook_id VARCHAR(255) NOT NULL,
 *     event VARCHAR(255) NOT NULL,
 *     payload JSON,
 *     status VARCHAR(50),
 *     status_code INT,
 *     response TEXT,
 *     attempt INT DEFAULT 0,
 *     delivered_at DATETIME,
 *     error TEXT,
 *     created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 *   );
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class DatabaseDriver implements WebhookDriverInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $webhooksTable = 'webhooks',
        private readonly string $deliveriesTable = 'webhook_deliveries',
    ) {}

    public function register(Webhook $webhook): void
    {
        $sql = "INSERT INTO {$this->webhooksTable} (id, url, events, secret, active, headers, max_retries)
                VALUES (:id, :url, :events, :secret, :active, :headers, :max_retries)
                ON DUPLICATE KEY UPDATE url = VALUES(url), events = VALUES(events),
                secret = VALUES(secret), active = VALUES(active), headers = VALUES(headers),
                max_retries = VALUES(max_retries)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id'         => $webhook->id,
            'url'        => $webhook->url,
            'events'     => json_encode($webhook->events),
            'secret'     => $webhook->secret,
            'active'     => $webhook->active ? 1 : 0,
            'headers'    => json_encode($webhook->headers),
            'max_retries' => $webhook->maxRetries,
        ]);
    }

    public function unregister(string $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->webhooksTable} WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function find(string $id): ?Webhook
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->webhooksTable} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateWebhook($row) : null;
    }

    public function findByEvent(string $event): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->webhooksTable} WHERE active = 1");
        $stmt->execute();
        $webhooks = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $webhook = $this->hydrateWebhook($row);
            if ($webhook->listensFor($event)) {
                $webhooks[] = $webhook;
            }
        }
        return $webhooks;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->webhooksTable}");
        $webhooks = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $webhooks[] = $this->hydrateWebhook($row);
        }
        return $webhooks;
    }

    public function recordDelivery(WebhookDelivery $delivery): void
    {
        $sql = "INSERT INTO {$this->deliveriesTable}
                (id, webhook_id, event, payload, status, status_code, response, attempt, delivered_at, error)
                VALUES (:id, :webhook_id, :event, :payload, :status, :status_code, :response, :attempt, :delivered_at, :error)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id'           => $delivery->id,
            'webhook_id'   => $delivery->webhookId,
            'event'        => $delivery->event,
            'payload'      => json_encode($delivery->payload),
            'status'       => $delivery->status,
            'status_code'  => $delivery->statusCode,
            'response'     => $delivery->response,
            'attempt'      => $delivery->attempt,
            'delivered_at' => $delivery->deliveredAt?->format('Y-m-d H:i:s'),
            'error'        => $delivery->error,
        ]);
    }

    public function deliveries(string $webhookId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->deliveriesTable} WHERE webhook_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([$webhookId, $limit]);
        $deliveries = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $deliveries[] = $this->hydrateDelivery($row);
        }
        return $deliveries;
    }

    /** @param array<string, mixed> $row */
    private function hydrateWebhook(array $row): Webhook
    {
        return new Webhook(
            id: (string) $row['id'],
            url: (string) $row['url'],
            events: json_decode($row['events'] ?? '[]', true) ?: [],
            secret: $row['secret'] ?? null,
            active: (bool) ($row['active'] ?? true),
            headers: json_decode($row['headers'] ?? '{}', true) ?: [],
            maxRetries: (int) ($row['max_retries'] ?? 3),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateDelivery(array $row): WebhookDelivery
    {
        return new WebhookDelivery(
            id: (string) $row['id'],
            webhookId: (string) $row['webhook_id'],
            event: (string) $row['event'],
            payload: json_decode($row['payload'] ?? '{}', true) ?: [],
            status: (string) ($row['status'] ?? 'pending'),
            statusCode: isset($row['status_code']) ? (int) $row['status_code'] : null,
            response: $row['response'] ?? null,
            attempt: (int) ($row['attempt'] ?? 0),
            deliveredAt: isset($row['delivered_at']) ? new \DateTimeImmutable($row['delivered_at']) : null,
            error: $row['error'] ?? null,
        );
    }
}
