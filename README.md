# MonKeysLegion Webhooks

Outgoing and incoming webhook system for the MonKeysLegion framework.

## Features

- **HMAC-SHA256 signing** — secure webhook payload verification
- **Outgoing webhooks** — register, dispatch, and track deliveries
- **Incoming webhook verification** — PSR-15 middleware
- **Retry with exponential backoff** — configurable max retries
- **Delivery tracking** — success, failed, retrying, pending statuses
- **Multiple drivers** — InMemory (testing), Database (PDO)

## Installation

```bash
composer require monkeyscloud/monkeyslegion-webhooks
```

## Usage

### Outgoing Webhooks

```php
use MonkeysLegion\Webhooks\WebhookManager;

$manager = $container->get(WebhookManager::class);

// Register a webhook endpoint
$manager->register('orders.created', 'https://example.com/webhook', 'secret');

// Dispatch an event to all matching webhooks
$manager->dispatch('orders.created', ['order_id' => 123, 'total' => 99.99]);
```

### Incoming Webhook Verification

```php
// In your routes
#[Route('POST', '/webhook/stripe')]
#[Middleware(['webhook.verify:stripe'])]
public function handleStripe(ServerRequestInterface $request): Response
{
    $payload = $request->getParsedBody();
    // Payload is already verified
}
```

### Manual Signature Verification

```php
use MonkeysLegion\Webhooks\WebhookSigner;

$signer = new WebhookSigner('your-secret');
$signature = $signer->sign($payload);

if ($signer->verify($payload, $signature)) {
    // Verified!
}
```

## License

MIT © MonKeysCloud
