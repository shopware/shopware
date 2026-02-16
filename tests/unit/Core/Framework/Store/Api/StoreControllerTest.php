<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Api\StoreController;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreController::class)]
class StoreControllerTest extends TestCase
{
    public function testLogoutDelegatesToStoreClient(): void
    {
        $context = new Context(new AdminApiSource(Uuid::randomHex()));

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->expects($this->once())
            ->method('logout')
            ->with($context);

        $userRepository = $this->createMock(EntityRepository::class);

        $storeController = new StoreController(
            $storeClient,
            $userRepository,
            $this->createMock(AbstractExtensionDataProvider::class),
        );

        $response = $storeController->logout($context);

        static::assertSame(200, $response->getStatusCode());
    }
}
