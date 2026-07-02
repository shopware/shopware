<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookSigningSecretResolver::class)]
class WebhookSigningSecretResolverTest extends TestCase
{
    public function testUsesTheCurrentSecretNotTheOneCapturedWhenQueued(): void
    {
        // Rotation regression: the message carries the OLD secret; the app has rotated to NEW.
        $resolver = $this->resolver(appSecret: 'new-secret', deletedSecret: false);

        static::assertSame(
            'new-secret',
            $resolver->resolve($this->message(Uuid::randomHex(), carried: 'old-secret', appName: 'TestApp'))
        );
    }

    public function testFallsBackToTheDeletedAppsSecretWhenTheAppIsGone(): void
    {
        $resolver = $this->resolver(appSecret: false, deletedSecret: 'retained-secret');

        static::assertSame(
            'retained-secret',
            $resolver->resolve($this->message(Uuid::randomHex(), carried: 'old-secret', appName: 'TestApp'))
        );
    }

    public function testFallsBackToTheCarriedSecretWhenNoLiveOrDeletedSecretExists(): void
    {
        $resolver = $this->resolver(appSecret: false, deletedSecret: false);

        static::assertSame(
            'carried-secret',
            $resolver->resolve($this->message(Uuid::randomHex(), carried: 'carried-secret', appName: 'TestApp'))
        );
    }

    public function testNonAppWebhookKeepsItsCarriedSecretWithoutAnyLookup(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');
        $resolver = new WebhookSigningSecretResolver($connection, new DeletedAppsGateway($connection));

        static::assertSame(
            'carried-secret',
            $resolver->resolve($this->message(appId: null, carried: 'carried-secret', appName: null))
        );
    }

    private function resolver(string|false $appSecret, string|false $deletedSecret): WebhookSigningSecretResolver
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            static fn (string $sql): string|false => str_contains($sql, 'deleted_apps') ? $deletedSecret : $appSecret
        );

        return new WebhookSigningSecretResolver($connection, new DeletedAppsGateway($connection));
    }

    private function message(?string $appId, ?string $carried, ?string $appName): WebhookEventMessage
    {
        return new WebhookEventMessage(
            'event-id',
            ['source' => ['eventId' => 'event-id']],
            $appId,
            Uuid::randomHex(),
            '6.7.0.0',
            'https://example.com/webhook',
            $carried,
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            [],
            $appId,
            $appName,
        );
    }
}
