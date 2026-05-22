<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\ServiceRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[CoversClass(ServiceRepository::class)]
class ServiceRepositoryTest extends TestCase
{
    public function testFindByName(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyService');
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($app): array {
                self::assertServiceFilter($criteria);

                return [$app];
            },
        ]);

        $service = (new ServiceRepository($repository))->findByName('MyService', Context::createDefaultContext());

        static::assertInstanceOf(Service::class, $service);
        static::assertSame($app->getId(), $service->getId());
        static::assertSame('MyService', $service->getName());
    }

    public function testFindByIntegrationId(): void
    {
        $app = AppFixture::createAppEntity();
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($app): array {
                self::assertServiceFilter($criteria);

                return [$app];
            },
        ]);

        $service = (new ServiceRepository($repository))->findByIntegrationId('integration-id', Context::createDefaultContext());

        static::assertInstanceOf(Service::class, $service);
        static::assertSame($app->getId(), $service->getId());
    }

    public function testFindAll(): void
    {
        $app = AppFixture::createAppEntity();
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($app): array {
                self::assertServiceFilter($criteria);

                return [$app];
            },
        ]);

        $services = (new ServiceRepository($repository))->findAll(Context::createDefaultContext());

        static::assertCount(1, $services);
        static::assertSame($app->getId(), $services[0]->getId());
    }

    private static function assertServiceFilter(Criteria $criteria): void
    {
        static::assertContainsEquals(new EqualsFilter('selfManaged', true), $criteria->getFilters());
    }
}
