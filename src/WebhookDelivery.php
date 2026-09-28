<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks;

/**
 * MonKeysLegion Framework — Webhooks Package
 *
 * Webhook delivery record — tracks each delivery attempt.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final readonly class WebhookDelivery
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_RETRYING = 'retrying';

    /**
     * @param string               $id            Delivery ID.
     * @param string               $webhookId     Webhook ID.
     * @param string               $event         Event name.
     * @param array<string, mixed> $payload       Payload sent.
     * @param string               $status        Delivery status.
     * @param int|null             $statusCode    HTTP response code.
     * @param string|null          $response      Response body.
     * @param int                  $attempt       Attempt number.
     * @param \DateTimeImmutable|null $deliveredAt When delivered.
     * @param string|null          $error         Error message if failed.
     */
    public function __construct(
        public string $id,
        public string $webhookId,
        public string $event,
        public array $payload,
        public string $status = self::STATUS_PENDING,
        public ?int $statusCode = null,
        public ?string $response = null,
        public int $attempt = 0,
        public ?\DateTimeImmutable $deliveredAt = null,
        public ?string $error = null,
    ) {}

    /**
     * Whether this delivery was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Whether this delivery should be retried.
     */
    public function shouldRetry(int $maxRetries): bool
    {
        return $this->status === self::STATUS_FAILED && $this->attempt < $maxRetries;
    }
}
