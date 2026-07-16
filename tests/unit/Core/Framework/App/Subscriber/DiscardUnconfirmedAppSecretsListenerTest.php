<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\App\Subscriber\DiscardUnconfirmedAppSecretsListener;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(DiscardUnconfirmedAppSecretsListener::class)]
class DiscardUnconfirmedAppSecretsListenerTest extends TestCase
{
    public function testDiscardsUnconfirmedSecretsOfEveryPendingApp(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([['app-one', 'app-two']]);

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $discardedIds = [];
        $rotationService->expects($this->exactly(2))
            ->method('discardNow')
            ->willReturnCallback(static function (string $appId, Context $context) use (&$discardedIds): void {
                $discardedIds[] = $appId;
            });

        $listener = new DiscardUnconfirmedAppSecretsListener($appRepository, $rotationService);
        $listener(new ShopIdDeletedEvent());

        static::assertSame(['app-one', 'app-two'], $discardedIds);
    }

    public function testDoesNothingWhenNoAppHasUnconfirmedSecrets(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([[]]);

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->never())->method('discardNow');

        $listener = new DiscardUnconfirmedAppSecretsListener($appRepository, $rotationService);
        $listener(new ShopIdDeletedEvent());
    }
}
