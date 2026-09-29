<?php
declare(strict_types=1);

namespace MonkeysLegion\Webhooks\Providers;

use MonkeysLegion\Webhooks\Drivers\InMemoryDriver;
use MonkeysLegion\Webhooks\Drivers\WebhookDriverInterface;
use MonkeysLegion\Webhooks\WebhookManager;
use MonkeysLegion\Webhooks\WebhookSigner;

/**
 * MonkeysLegion Framework — Webhooks Package
 *
 * Service provider for webhook registration.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class WebhookServiceProvider
{
    public function register(array $config, callable $register, callable $resolve): void
    {
        $register(WebhookSigner::class, function() use ($config): WebhookSigner {
            return new WebhookSigner(
                $config['secret'] ?? '',
                $config['algorithm'] ?? 'sha256',
            );
        });

        $register(WebhookDriverInterface::class, function() use ($config, $resolve): WebhookDriverInterface {
            $driver = $config['driver'] ?? 'memory';
            return match ($driver) {
                'database' => new \MonkeysLegion\Webhooks\Drivers\DatabaseDriver(
                    $resolve('db'),
                ),
                'memory', 'array' => new InMemoryDriver(),
                default => new InMemoryDriver(),
            };
        });

        $register(WebhookManager::class, function() use ($resolve, $config): WebhookManager {
            return new WebhookManager(
                $resolve(WebhookDriverInterface::class),
                $resolve(WebhookSigner::class),
                $config['timeout'] ?? 30,
            );
        });
    }
}
