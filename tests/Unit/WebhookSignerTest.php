<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks\Tests\Unit;

use MonkeysLegion\Webhooks\WebhookSigner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the WebhookSigner.
 */
final class WebhookSignerTest extends TestCase
{
    private WebhookSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new WebhookSigner('test-secret-key');
    }

    #[Test]
    public function sign_returns_algorithm_prefixed_hex(): void
    {
        $signature = $this->signer->sign('{"event":"test"}');

        self::assertStringStartsWith('sha256=', $signature);
        // After prefix, should be a 64-char hex string
        $hash = substr($signature, 7);
        self::assertSame(64, strlen($hash));
    }

    #[Test]
    public function sign_is_deterministic(): void
    {
        $payload = '{"event":"test"}';

        $sig1 = $this->signer->sign($payload);
        $sig2 = $this->signer->sign($payload);

        self::assertSame($sig1, $sig2);
    }

    #[Test]
    public function sign_differs_for_different_payloads(): void
    {
        $sig1 = $this->signer->sign('{"event":"a"}');
        $sig2 = $this->signer->sign('{"event":"b"}');

        self::assertNotSame($sig1, $sig2);
    }

    #[Test]
    public function verify_accepts_correct_signature(): void
    {
        $payload = '{"event":"test"}';
        $signature = $this->signer->sign($payload);

        self::assertTrue($this->signer->verify($payload, $signature));
    }

    #[Test]
    public function verify_rejects_wrong_signature(): void
    {
        $payload = '{"event":"test"}';

        self::assertFalse($this->signer->verify($payload, 'sha256=wrong'));
    }

    #[Test]
    public function verify_rejects_wrong_payload(): void
    {
        $signature = $this->signer->sign('{"event":"a"}');

        self::assertFalse($this->signer->verify('{"event":"b"}', $signature));
    }

    #[Test]
    public function verify_rejects_different_secret(): void
    {
        $otherSigner = new WebhookSigner('different-secret');
        $payload = '{"event":"test"}';
        $signature = $otherSigner->sign($payload);

        self::assertFalse($this->signer->verify($payload, $signature));
    }

    #[Test]
    public function verify_loose_accepts_without_prefix(): void
    {
        $payload = '{"event":"test"}';
        $signature = $this->signer->sign($payload);
        $hashOnly = substr($signature, 7); // Remove 'sha256=' prefix

        self::assertTrue($this->signer->verifyLoose($payload, $hashOnly));
    }

    #[Test]
    public function verify_loose_accepts_with_prefix(): void
    {
        $payload = '{"event":"test"}';
        $signature = $this->signer->sign($payload);

        self::assertTrue($this->signer->verifyLoose($payload, $signature));
    }

    #[Test]
    public function different_algorithms_produce_different_signatures(): void
    {
        $sha256Signer = new WebhookSigner('secret', 'sha256');
        $sha512Signer = new WebhookSigner('secret', 'sha512');

        $sig256 = $sha256Signer->sign('test');
        $sig512 = $sha512Signer->sign('test');

        self::assertStringStartsWith('sha256=', $sig256);
        self::assertStringStartsWith('sha512=', $sig512);
        self::assertNotSame($sig256, $sig512);
    }
}
