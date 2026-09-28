<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks\Middleware;

use MonkeysLegion\Webhooks\WebhookSigner;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonKeysLegion Framework — Webhooks Package
 *
 * Middleware to verify incoming webhook signatures.
 *
 * Reads the request body, computes the expected HMAC-SHA256 signature,
 * and compares it to the X-Webhook-Signature header using timing-safe
 * comparison. Returns 401 if verification fails.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class VerifyWebhookSignatureMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly WebhookSigner $signer,
        private readonly string $signatureHeader = 'X-Webhook-Signature',
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $signature = $request->getHeaderLine($this->signatureHeader);

        if ($signature === '') {
            return $this->unauthorized('Missing webhook signature header.');
        }

        $body = (string) $request->getBody();

        if (!$this->signer->verifyLoose($body, $signature)) {
            return $this->unauthorized('Invalid webhook signature.');
        }

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString(json_encode(['error' => $message])),
            401,
            ['Content-Type' => 'application/json'],
        );
    }
}
