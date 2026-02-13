<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Event\ShopwareAccountLogoutEvent;
use Shopware\Core\Framework\Store\Services\ExtensionLoader;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreClient::class)]
class StoreClientTest extends TestCase
{
    public function testLogoutRemovesStoreTokenAndDispatchesLogoutEvent(): void
    {
        $context = new Context(new AdminApiSource(Uuid::randomHex()));

        $storeService = $this->createMock(StoreService::class);
        $storeService->expects($this->once())
            ->method('removeStoreToken')
            ->with($context);

        $eventDispatcher = new CollectingEventDispatcher();

        $storeClient = new StoreClient(
            [],
            $storeService,
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractStoreRequestOptionsProvider::class),
            $this->createMock(ExtensionLoader::class),
            $this->createMock(ClientInterface::class),
            $this->createMock(InstanceService::class),
            new RequestStack(),
            $this->createMock(CacheInterface::class),
            $eventDispatcher,
        );

        $storeClient->logout($context);

        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertInstanceOf(ShopwareAccountLogoutEvent::class, $eventDispatcher->getEvents()[0]);
    }
}
