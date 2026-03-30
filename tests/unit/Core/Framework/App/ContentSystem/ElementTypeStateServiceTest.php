<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeCollection;
use Shopware\Core\Framework\App\ContentSystem\ElementTypeStateService;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ElementTypeStateService::class)]
class ElementTypeStateServiceTest extends TestCase
{
    #[TestDox('activates inactive element types for given app')]
    public function testActivateElementTypesUpdatesInactiveToActive(): void
    {
        $appId = 'test-app-id';

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            static function (Criteria $criteria): array {
                $filters = $criteria->getFilters();
                static::assertCount(2, $filters);

                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertSame('appId', $filters[0]->getField());
                static::assertSame('test-app-id', $filters[0]->getValue());

                static::assertInstanceOf(EqualsFilter::class, $filters[1]);
                static::assertSame('active', $filters[1]->getField());
                static::assertFalse($filters[1]->getValue());

                return ['type-id-1', 'type-id-2'];
            },
        ]);

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        $service = new ElementTypeStateService($repo, $registry);
        $service->activateElementTypes($appId, Context::createDefaultContext());

        static::assertCount(1, $repo->updates);
        static::assertCount(2, $repo->updates[0]);
        static::assertSame(['id' => 'type-id-1', 'active' => true], $repo->updates[0][0]);
        static::assertSame(['id' => 'type-id-2', 'active' => true], $repo->updates[0][1]);
    }

    #[TestDox('deactivates active element types for given app')]
    public function testDeactivateElementTypesUpdatesActiveToInactive(): void
    {
        $appId = 'test-app-id';

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            static function (Criteria $criteria): array {
                $filters = $criteria->getFilters();
                static::assertCount(2, $filters);

                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertSame('appId', $filters[0]->getField());
                static::assertSame('test-app-id', $filters[0]->getValue());

                static::assertInstanceOf(EqualsFilter::class, $filters[1]);
                static::assertSame('active', $filters[1]->getField());
                static::assertTrue($filters[1]->getValue());

                return ['type-id-1'];
            },
        ]);

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        $service = new ElementTypeStateService($repo, $registry);
        $service->deactivateElementTypes($appId, Context::createDefaultContext());

        static::assertCount(1, $repo->updates);
        static::assertCount(1, $repo->updates[0]);
        static::assertSame(['id' => 'type-id-1', 'active' => false], $repo->updates[0][0]);
    }

    #[TestDox('skips update and cache invalidation when no element types match for activation')]
    public function testActivateElementTypesSkipsUpdateWhenNoTypesFound(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            [],
        ]);

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        $service = new ElementTypeStateService($repo, $registry);
        $service->activateElementTypes('app-without-types', Context::createDefaultContext());

        static::assertCount(0, $repo->updates);
    }

    #[TestDox('skips update and cache invalidation when no element types match for deactivation')]
    public function testDeactivateElementTypesSkipsUpdateWhenNoTypesFound(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            [],
        ]);

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        $service = new ElementTypeStateService($repo, $registry);
        $service->deactivateElementTypes('app-without-types', Context::createDefaultContext());

        static::assertCount(0, $repo->updates);
    }
}
