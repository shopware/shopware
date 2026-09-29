<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceStorage::class)]
class ServiceStorageTest extends TestCase
{
    public function testFindByName(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyService');
        $repository = StaticEntityRepository::of(AppCollection::class, [
            static function (Criteria $criteria) use ($app): array {
                self::assertServiceFilter($criteria);

                return [$app];
            },
        ]);

        $service = (new ServiceStorage($repository))->findByName('MyService', Context::createDefaultContext());

        static::assertInstanceOf(Service::class, $service);
        static::assertSame($app->getId(), $service->id);
        static::assertSame('MyService', $service->name);
    }

    public function testFindByIntegrationId(): void
    {
        $app = AppFixture::createAppEntity();
        $repository = StaticEntityRepository::of(AppCollection::class, [
            static function (Criteria $criteria) use ($app): array {
                self::assertServiceFilter($criteria);

                return [$app];
            },
        ]);

        $service = (new ServiceStorage($repository))->findByIntegrationId('integration-id', Context::createDefaultContext());

        static::assertInstanceOf(Service::class, $service);
        static::assertSame($app->getId(), $service->id);
    }

    public function testFindByNameAndIntegrationId(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyService');
        $repository = StaticEntityRepository::of(AppCollection::class, [
            static function (Criteria $criteria) use ($app): array {
                self::assertServiceFilter($criteria);

                return [$app];
            },
        ]);

        $service = (new ServiceStorage($repository))->findByNameAndIntegrationId('MyService', 'integration-id', Context::createDefaultContext());

        static::assertInstanceOf(Service::class, $service);
        static::assertSame($app->getId(), $service->id);
        static::assertSame('MyService', $service->name);
    }

    public function testFindAll(): void
    {
        $app = AppFixture::createAppEntity();
        $repository = StaticEntityRepository::of(AppCollection::class, [
            static function (Criteria $criteria) use ($app): array {
                self::assertServiceFilter($criteria);

                return [$app];
            },
        ]);

        $services = (new ServiceStorage($repository))->findAll(Context::createDefaultContext());

        static::assertCount(1, $services);
        static::assertSame($app->getId(), $services[0]->id);
    }

    private static function assertServiceFilter(Criteria $criteria): void
    {
        static::assertContainsEquals(new EqualsFilter('selfManaged', true), $criteria->getFilters());
        static::assertTrue($criteria->hasAssociation('aclRole'));
        static::assertFalse($criteria->hasAssociation('app'));
    }
}
