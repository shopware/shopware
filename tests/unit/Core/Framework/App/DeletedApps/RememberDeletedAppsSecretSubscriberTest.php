<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\DeletedApps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\DeletedApps\RememberDeletedAppsSecretSubscriber;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(RememberDeletedAppsSecretSubscriber::class)]
class RememberDeletedAppsSecretSubscriberTest extends TestCase
{
    private DeletedAppsGateway&MockObject $deletedAppsGateway;

    /**
     * @var StaticEntityRepository<AppCollection>
     */
    private StaticEntityRepository $appRepository;

    private RememberDeletedAppsSecretSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->deletedAppsGateway = $this->createMock(DeletedAppsGateway::class);
        $this->appRepository = new StaticEntityRepository([]);

        $this->subscriber = new RememberDeletedAppsSecretSubscriber(
            $this->appRepository,
            $this->deletedAppsGateway
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $this->deletedAppsGateway->expects($this->never())->method('deleteSecretForApp');

        static::assertSame([
            AppDeletedEvent::class => 'saveSecretFromDeletedApp',
            AppInstalledEvent::class => 'removeDeletedAppSecret',
            ShopIdChangedEvent::class => 'purgeOldSecretsAfterShopIdChange',
            ShopIdDeletedEvent::class => 'purgeOldSecretsAfterShopIdDeletion',
        ], RememberDeletedAppsSecretSubscriber::getSubscribedEvents());
    }

    public function testSaveSecretFromDeletedApp(): void
    {
        $appId = Uuid::randomHex();
        $event = new AppDeletedEvent(
            $appId,
            Context::createDefaultContext()
        );

        $foundApp = new AppEntity();
        $foundApp->setId($appId);
        $foundApp->setName('test-app');
        $foundApp->setAppSecret('secret-123');

        $this->appRepository->searches = [[$foundApp]];

        $this->deletedAppsGateway->expects($this->once())
            ->method('insertSecretForDeletedApp')
            ->with('test-app', 'secret-123');

        $this->subscriber->saveSecretFromDeletedApp($event);
    }

    public function testWhenAppHasNoSecretNothingIsSaved(): void
    {
        $appId = Uuid::randomHex();
        $event = new AppDeletedEvent(
            $appId,
            Context::createDefaultContext()
        );

        $foundApp = new AppEntity();
        $foundApp->setId($appId);
        $foundApp->setName('test-app');

        $this->appRepository->searches = [[$foundApp]];

        $this->deletedAppsGateway->expects($this->never())
            ->method('insertSecretForDeletedApp');

        $this->subscriber->saveSecretFromDeletedApp($event);
    }

    public function testRemoveDeletedAppSecret(): void
    {
        $app = new AppEntity();
        $app->setName('test-app');

        $event = new AppInstalledEvent(
            $app,
            static::createStub(Manifest::class),
            Context::createDefaultContext()
        );

        $this->deletedAppsGateway->expects($this->once())
            ->method('deleteSecretForApp')
            ->with('test-app');

        $this->subscriber->removeDeletedAppSecret($event);
    }

    public function testSameIdentityShopIdChangePreservesDeletedAppSecrets(): void
    {
        $this->deletedAppsGateway->expects($this->never())->method('purgeOldSecrets');

        $this->subscriber->purgeOldSecretsAfterShopIdChange(new ShopIdChangedEvent(
            ShopId::v2('same-shop', ['app_url' => 'https://new.example.com']),
            ShopId::v2('same-shop', ['app_url' => 'https://old.example.com']),
        ));
    }

    public function testNewIdentityShopIdChangePurgesDeletedAppSecrets(): void
    {
        $this->deletedAppsGateway->expects($this->once())->method('purgeOldSecrets');

        $this->subscriber->purgeOldSecretsAfterShopIdChange(new ShopIdChangedEvent(
            ShopId::v2('new-shop'),
            ShopId::v2('old-shop'),
        ));
    }

    public function testInitialShopIdCreationPurgesDeletedAppSecrets(): void
    {
        $this->deletedAppsGateway->expects($this->once())->method('purgeOldSecrets');

        $this->subscriber->purgeOldSecretsAfterShopIdChange(new ShopIdChangedEvent(
            ShopId::v2('new-shop'),
            null,
        ));
    }

    public function testShopIdDeletionPurgesDeletedAppSecrets(): void
    {
        $this->deletedAppsGateway->expects($this->once())->method('purgeOldSecrets');

        $this->subscriber->purgeOldSecretsAfterShopIdDeletion(new ShopIdDeletedEvent());
    }
}
