<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\DeleteUnusedGuestCustomerService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeleteUnusedGuestCustomerService::class)]
class DeleteUnusedGuestCustomerServiceTest extends TestCase
{
    public function testCountNoConfig(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects(static::once())
            ->method('getInt')
            ->willReturn(0);

        $service = new DeleteUnusedGuestCustomerService(
            $this->createMock(EntityRepository::class),
            $configService
        );

        $result = $service->countUnusedCustomers(Context::createDefaultContext());

        static::assertSame(0, $result);
    }

    public function testCountConfig(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects(static::once())
            ->method('getInt')
            ->willReturn(1);

        $service = new DeleteUnusedGuestCustomerService(
            new StaticEntityRepository([[$this->createCustomer(), $this->createCustomer()]]),
            $configService
        );

        $result = $service->countUnusedCustomers(Context::createDefaultContext());

        static::assertSame(2, $result);
    }

    public function testDeleteCustomer(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects(static::once())
            ->method('getInt')
            ->willReturn(1);

        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $deleteIds = \array_values(\array_map(static fn (string $id) => ['id' => $id], $ids));
        $searchResultIds = \array_map(static fn (string $id) => ['primaryKey' => $id, 'data' => []], $ids);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(3, $searchResultIds, new Criteria(), Context::createDefaultContext()));
        $customerRepository
            ->expects(static::once())
            ->method('delete')
            ->with($deleteIds);

        $service = new DeleteUnusedGuestCustomerService(
            $customerRepository,
            $configService
        );

        $result = $service->deleteUnusedCustomers(Context::createDefaultContext());

        static::assertSame($deleteIds, $result);
    }

    protected function createCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        return $customer;
    }
}
