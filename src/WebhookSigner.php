<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks;

/**
 * MonKeysLegion Framework — Webhooks Package
 *
 * HMAC-SHA256 signer for webhook payloads.
 *
 * Signs payloads with a shared secret and verifies incoming signatures.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class WebhookSigner
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm = 'sha256',
    ) {}

    /**
     * Sign a payload and return the signature header value.
     *
     * Format: "sha256=<hex digest>" (compatible with GitHub/Stripe style).
     */
    public function sign(string $payload): string
    {
        return $this->algorithm . '=' . hash_hmac($this->algorithm, $payload, $this->secret);
    }

    /**
     * Verify an incoming webhook signature.
     *
     * @param string $payload    The raw request body.
     * @param string $signature  The signature header value.
     */
    public function verify(string $payload, string $signature): bool
    {
        $expected = $this->sign($payload);

        if (strlen($expected) !== strlen($signature)) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    /**
     * Verify using timing-safe comparison with optional prefix matching.
     *
     * Some providers send the signature without the algorithm prefix.
     */
    public function verifyLoose(string $payload, string $signature): bool
    {
        // Try exact match first
        if ($this->verify($payload, $signature)) {
            return true;
        }

        // Try without algorithm prefix
        $hash = hash_hmac($this->algorithm, $payload, $this->secret);
        if (strlen($hash) === strlen($signature)) {
            return hash_equals($hash, $signature);
        }

        return false;
    }
}
