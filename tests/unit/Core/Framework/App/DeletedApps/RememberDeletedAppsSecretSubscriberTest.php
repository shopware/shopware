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
        static::assertSame([
            AppDeletedEvent::class => 'saveSecretFromDeletedApp',
            AppInstalledEvent::class => 'removeDeletedAppSecret',
            ShopIdChangedEvent::class => 'purgeOldSecrets',
            ShopIdDeletedEvent::class => 'purgeOldSecrets',
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

    public function testOldSecretIsDeletedWhenAppIsSucessfullyInstalled(): void
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
            $this->createMock(Manifest::class),
            Context::createDefaultContext()
        );

        $this->deletedAppsGateway->expects($this->once())
            ->method('deleteSecretForApp')
            ->with('test-app');

        $this->subscriber->removeDeletedAppSecret($event);
    }

    public function testPurgeOldSecrets(): void
    {
        $this->deletedAppsGateway->expects($this->once())
            ->method('purgeOldSecrets');

        $this->subscriber->purgeOldSecrets();
    }
}
