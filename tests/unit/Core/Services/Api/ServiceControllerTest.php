<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Services\Api\ServiceController;
use Shopware\Core\Services\Message\UpdateServiceMessage;
use Shopware\Core\Services\ServicesException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(ServiceController::class)]
class ServiceControllerTest extends TestCase
{
    private AppEntity $app;

    /**
     * @var StaticEntityRepository<AppCollection>
     */
    private StaticEntityRepository $appRepo;

    private MessageBusInterface&MockObject $bus;

    protected function setUp(): void
    {
        $this->app = new AppEntity();
        $this->app->setId(Uuid::randomHex());
        $this->app->setUniqueIdentifier(Uuid::randomHex());
        $this->app->assign(['name' => 'MyCoolService', 'integrationId' => 'CCDD']);

        $this->appRepo = new StaticEntityRepository([[$this->app]]);

        $this->bus = $this->createMock(MessageBusInterface::class);
    }

    public function testExceptionIsThrownIfServiceDoesNotExist(): void
    {
        static::expectExceptionObject(ServicesException::notFound('integrationId', 'CCDD'));

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[]]);

        $this->bus->expects(static::never())->method('dispatch');

        $controller = new ServiceController($appRepo, $this->bus);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testExceptionIsThrownIfNotApiSource(): void
    {
        $source = new ShopApiSource('AABB');
        static::expectExceptionObject(ServicesException::updateRequiresAdminApiSource($source));

        $this->bus->expects(static::never())->method('dispatch');

        $controller = new ServiceController($this->appRepo, $this->bus);

        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testExceptionIsThrownIfNoIntegrationId(): void
    {
        static::expectExceptionObject(ServicesException::updateRequiresIntegration());

        $this->bus->expects(static::never())->method('dispatch');

        $controller = new ServiceController($this->appRepo, $this->bus);

        $source = new AdminApiSource('AABB');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }

    public function testUpdateIsTriggered(): void
    {
        $this->bus->expects(static::once())->method('dispatch')->willReturnCallback(function (UpdateServiceMessage $msg) {
            static::assertSame('MyCoolService', $msg->name);

            return new Envelope($msg, []);
        });

        $controller = new ServiceController($this->appRepo, $this->bus);

        $source = new AdminApiSource('AABB', 'CCDD');
        $context = Context::createDefaultContext($source);

        $controller->triggerUpdate($context);
    }
}
