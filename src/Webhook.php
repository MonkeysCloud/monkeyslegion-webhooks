<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks;

/**
 * MonKeysLegion Framework — Webhooks Package
 *
 * Webhook entity — represents a registered webhook endpoint.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final readonly class Webhook
{
    /**
     * @param string               $id           Unique webhook ID.
     * @param string               $url          Target URL.
     * @param list<string>         $events       Event names this webhook listens to.
     * @param string|null          $secret       HMAC signing secret.
     * @param bool                 $active       Whether this webhook is active.
     * @param array<string, mixed> $headers      Extra HTTP headers to send.
     * @param int                  $maxRetries   Maximum retry attempts.
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $events,
        public ?string $secret = null,
        public bool $active = true,
        public array $headers = [],
        public int $maxRetries = 3,
    ) {}

    /**
     * Check if this webhook listens for a given event.
     */
    public function listensFor(string $event): bool
    {
        return in_array($event, $this->events, true) || in_array('*', $this->events, true);
    }
}
