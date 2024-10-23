<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class CacheInvalidationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    private CacheInvalidator&MockObject $cacheInvalidatorMock;

    private CacheInvalidationSubscriber $cacheInvalidationSubscriber;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();

        $this->cacheInvalidatorMock = $this->createMock(CacheInvalidator::class);
        $this->cacheInvalidationSubscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidatorMock,
            $this->getContainer()->get(Connection::class),
            false,
            false
        );
    }

    public function testInvalidateDetailRouteLoadsParentProductIdsRework(): void
    {
        Feature::skipTestIfInActive('cache_rework', $this);

        $scenarios = [
            'parent-product' => [
                'invalidate' => ['p1'],
                'expected' => ['p1'],
            ],
            'single-product-with-parent' => [
                'invalidate' => ['p2'],
                'expected' => ['p1'],
            ],
            'multiple-products-with-parent' => [
                'invalidate' => ['p2', 'p3'],
                'expected' => ['p1'],
            ],
            'multiple-products-with-parent-and-without-parent' => [
                'invalidate' => ['p2', 'p3', 'p4'],
                'expected' => ['p1', 'p4'],
            ],
            'parent-and-child' => [
                'invalidate' => ['p1', 'p2'],
                'expected' => ['p1'],
            ],
            'multiple-child-same-parent' => [
                'invalidate' => ['p1', 'p2', 'p3'],
                'expected' => ['p1'],
            ],
        ];

        $this->createProduct($this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p2'), $this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p3'), $this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p4'));

        $listener = new class {
            /**
             * @param array<string> $tags
             */
            public function __construct(public array $tags = [])
            {
            }

            public function __invoke(InvalidateCacheEvent $event): void
            {
                $this->tags = array_values($event->getKeys());
            }
        };

        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            InvalidateCacheEvent::class,
            $listener
        );

        $subscriber = static::getContainer()->get(CacheInvalidationSubscriber::class);

        foreach ($scenarios as $desc => $scenario) {
            /** @var list<string> $productsIds */
            $productsIds = array_map(fn (string $product) => $this->ids->get($product), $scenario['invalidate']);

            $subscriber->invalidateProduct(new InvalidateProductCache($productsIds));

            $actual = $listener->tags;
            $expected = array_map(fn (string $product) => ProductDetailRoute::buildName($this->ids->get($product)), $scenario['expected']);

            sort($actual);
            sort($expected);

            static::assertSame($expected, $actual, 'Failed scenario: ' . $desc);
        }
    }

    public function testItInvalidatesCacheIfPropertyGroupIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = $this->getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'sortingType' => PropertyGroupDefinition::SORTING_TYPE_POSITION,
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects(static::once())
            ->method('invalidate')
            ->with(static::countOf(1));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyGroupTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = $this->getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'name' => 'new name',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects(static::once())
            ->method('invalidate')
            ->with(static::countOf(1));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfPropertyOptionIsAddedToGroup(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = $this->getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'options' => [
                    [
                        'id' => $this->ids->get('new-property'),
                        'name' => 'new-property',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects(static::once())
            ->method('invalidate')
            ->with(static::countOf(0));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyOptionIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = $this->getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-assigned'),
                'colorHexCode' => '#000000',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects(static::once())
            ->method('invalidate')
            ->with(static::countOf(1));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfUnassignedPropertyOptionIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = $this->getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-unassigned'),
                'colorHexCode' => '#000000',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects(static::once())
            ->method('invalidate')
            ->with(static::countOf(0));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyOptionTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = $this->getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-assigned'),
                'name' => 'updated',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects(static::once())
            ->method('invalidate')
            ->with(static::countOf(1));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfUnassignedPropertyOptionTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = $this->getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-unassigned'),
                'name' => 'updated',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects(static::once())
            ->method('invalidate')
            ->with(static::countOf(0));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfProductIsCreatedWithExistingOption(): void
    {
        $this->insertDefaultPropertyGroup();

        $builder = new ProductBuilder($this->ids, 'product2');
        $builder->price(10)
            ->property('property-assigned', '');

        $event = $this->getContainer()->get('product.repository')->create([$builder->build()], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects(static::once())
            ->method('invalidate')
            ->with(static::countOf(0));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testInvalidateDetailRouteLoadsParentProductIds(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);
        $scenarios = [
            'parent-product' => [
                'invalidate' => ['p1'],
                'expected' => ['p1'],
            ],
            'single-product-with-parent' => [
                'invalidate' => ['p2'],
                'expected' => ['p1', 'p2'],
            ],
            'multiple-products-with-parent' => [
                'invalidate' => ['p2', 'p3'],
                'expected' => ['p1', 'p2', 'p3'],
            ],
            'multiple-products-with-parent-and-without-parent' => [
                'invalidate' => ['p2', 'p3', 'p4'],
                'expected' => ['p1', 'p2', 'p3', 'p4'],
            ],
            'parent-and-child' => [
                'invalidate' => ['p1', 'p2'],
                'expected' => ['p1', 'p2'],
            ],
            'multiple-child-same-parent' => [
                'invalidate' => ['p1', 'p2', 'p3'],
                'expected' => ['p1', 'p2', 'p3'],
            ],
        ];

        $this->createProduct($this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p2'), $this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p3'), $this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p4'));

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $listener = new class {
            /**
             * @param array<string> $tags
             */
            public function __construct(public array $tags = [])
            {
            }

            public function __invoke(InvalidateCacheEvent $event): void
            {
                $this->tags = array_values($event->getKeys());
            }
        };

        $eventDispatcher->addListener(InvalidateCacheEvent::class, $listener);
        $subscriber = static::getContainer()->get(CacheInvalidationSubscriber::class);

        foreach ($scenarios as $scenario) {
            /** @var list<string> $productsIds */
            $productsIds = array_map(fn (string $product) => $this->ids->get($product), $scenario['invalidate']);

            $subscriber->invalidateDetailRoute(new ProductNoLongerAvailableEvent($productsIds, Context::createDefaultContext()));

            // use ProductDetailRoute::buildName()
            static::assertSame(
                array_map(fn (string $product) => CachedProductDetailRoute::buildName($this->ids->get($product)), $scenario['expected']),
                $listener->tags
            );
        }
    }

    private function insertDefaultPropertyGroup(): void
    {
        $groupRepository = $this->getContainer()->get('property_group.repository');

        $data = [
            'id' => $this->ids->get('group1'),
            'name' => 'group1',
            'sortingType' => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
            'displayType' => PropertyGroupDefinition::DISPLAY_TYPE_TEXT,
            'options' => [
                [
                    'id' => $this->ids->get('property-assigned'),
                    'name' => 'assigned',
                ],
                [
                    'id' => $this->ids->get('property-unassigned'),
                    'name' => 'unassigned',
                ],
            ],
        ];

        $groupRepository->create([$data], Context::createDefaultContext());

        $builder = new ProductBuilder($this->ids, 'product1');
        $builder->price(10)
            ->property('property-assigned', '');

        $this->getContainer()->get('product.repository')->create([$builder->build()], Context::createDefaultContext());
    }

    private function createProduct(string $id, ?string $parentId = null): void
    {
        $product = [
            'id' => $id,
            'parent_id' => $parentId,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'stock' => 5,
            'available_stock' => 5,
        ];

        $this->connection->insert('product', $product);
    }
}
