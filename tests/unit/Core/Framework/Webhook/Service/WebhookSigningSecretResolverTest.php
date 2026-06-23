<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookSigningSecretResolver::class)]
class WebhookSigningSecretResolverTest extends TestCase
{
    public function testUsesTheCurrentSecretNotTheOneCapturedWhenQueued(): void
    {
        $appId = Uuid::randomHex();
        $resolver = new WebhookSigningSecretResolver(
            $this->appRepository(new AppCollection([$this->app($appId, 'new-secret')])),
            $this->deletedAppsGateway(false),
        );

        static::assertSame(
            'new-secret',
            $resolver->resolve($this->message($appId, carried: 'old-secret', appName: 'TestApp'))
        );
    }

    public function testFallsBackToTheDeletedAppsSecretWhenTheAppIsGone(): void
    {
        $resolver = new WebhookSigningSecretResolver(
            $this->appRepository(new AppCollection([])),
            $this->deletedAppsGateway('retained-secret'),
        );

        static::assertSame(
            'retained-secret',
            $resolver->resolve($this->message(Uuid::randomHex(), carried: 'old-secret', appName: 'TestApp'))
        );
    }

    public function testFallsBackToTheCarriedSecretWhenNoLiveOrDeletedSecretExists(): void
    {
        $resolver = new WebhookSigningSecretResolver(
            $this->appRepository(new AppCollection([])),
            $this->deletedAppsGateway(false),
        );

        static::assertSame(
            'carried-secret',
            $resolver->resolve($this->message(Uuid::randomHex(), carried: 'carried-secret', appName: 'TestApp'))
        );
    }

    public function testNonAppWebhookKeepsItsCarriedSecret(): void
    {
        $resolver = new WebhookSigningSecretResolver(
            $this->appRepository(new AppCollection([])),
            $this->deletedAppsGateway(false),
        );

        static::assertSame(
            'carried-secret',
            $resolver->resolve($this->message(appId: null, carried: 'carried-secret', appName: null))
        );
    }

    public function testMemoizesTheSecretPerRunAndResetLooksItUpAgain(): void
    {
        $appId = Uuid::randomHex();
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            new AppCollection([$this->app($appId, 'first')]),
            new AppCollection([$this->app($appId, 'second')]),
        ]);
        $resolver = new WebhookSigningSecretResolver($appRepository, $this->deletedAppsGateway(false));
        $message = $this->message($appId, carried: 'carried-secret', appName: 'TestApp');

        static::assertSame('first', $resolver->resolve($message));
        static::assertSame('first', $resolver->resolve($message), 'repeat lookups are served from the per-run cache');

        $resolver->reset();

        static::assertSame('second', $resolver->resolve($message), 'after reset the secret is looked up again');
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    private function appRepository(AppCollection $result): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([$result]);

        return $repository;
    }

    private function deletedAppsGateway(string|false $deletedSecret): DeletedAppsGateway
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($deletedSecret);

        return new DeletedAppsGateway($connection);
    }

    private function app(string $id, ?string $secret): AppEntity
    {
        $app = new AppEntity();
        $app->setUniqueIdentifier($id);
        $app->setId($id);
        $app->setName('TestApp');
        $app->setAppSecret($secret);

        return $app;
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
