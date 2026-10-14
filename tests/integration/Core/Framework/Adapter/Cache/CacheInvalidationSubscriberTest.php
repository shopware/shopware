<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Backtrace\BacktraceCollector;
use Shopware\Core\Framework\Util\Backtrace\Frame;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
class CacheInvalidationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private CacheInvalidationSubscriber $cacheInvalidationSubscriber;

    private LoggerInterface&MockObject $logger;

    private BacktraceCollector&MockObject $backtraceCollector;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->backtraceCollector = $this
            ->getMockBuilder(BacktraceCollector::class)
            ->onlyMethods(['collectDebugBacktrace'])
            ->getMock();

        $cacheInvalidator = new CacheInvalidator(
            [static::createStub(TagAwareAdapterInterface::class)],
            static::createStub(RedisInvalidatorStorage::class),
            new EventDispatcher(),
            $this->logger,
            new RequestStack([new Request()]),
            static::createStub(TagAwareAdapterInterface::class),
            false,
            false,
            true,
            $this->backtraceCollector,
            new NativeClock(),
        );

        $this->cacheInvalidationSubscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            static::getContainer()->get(Connection::class),
            true,
        );
    }

    public function testItInvalidatesCacheIfPropertyIsDeleted(): void
    {
        $this->insertDefaultPropertyGroup();

        $productPropertyRepository = static::getContainer()->get('product_property.repository');
        $event = $productPropertyRepository->delete([
            [
                'productId' => $this->ids->get('product1'),
                'optionId' => $this->ids->get('property-assigned'),
            ],
        ], Context::createDefaultContext());

        $this->backtraceCollector->expects($this->once())->method('collectDebugBacktrace')->willReturn([
            [
                'class' => CacheInvalidationSubscriber::class,
                'function' => 'invalidatePropertyFilters',
            ],
        ]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Purged tags (1).',
                static::callback(static function (array $context): bool {
                    static::assertCount(1, $context['tags']);
                    static::assertSame(
                        (new Frame(
                            'Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber',
                            'invalidatePropertyFilters'
                        ))->toArray(),
                        $context['caller']
                    );

                    return true;
                })
            );

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfNoPropertyIsDeleted(): void
    {
        $this->backtraceCollector->expects($this->never())->method(static::anything());

        $this->insertDefaultPropertyGroup();

        $groupRepository = static::getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'sortingType' => PropertyGroupDefinition::SORTING_TYPE_POSITION,
            ],
        ], Context::createDefaultContext());

        $this->logger->expects($this->never())->method('log');
        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testOnlyGroupCategoriesInvalidateTheListingsAboveThem(): void
    {
        $context = Context::createDefaultContext();

        static::getContainer()->get('product_stream.repository')->create([[
            'id' => $this->ids->create('stream'),
            'name' => 'stream',
            'filters' => [['type' => 'equals', 'field' => 'active', 'value' => '1']],
        ]], $context);

        static::getContainer()->get('category.repository')->create([[
            'id' => $this->ids->create('root'),
            'name' => 'root',
            'children' => [[
                'id' => $this->ids->create('parent'),
                'name' => 'parent',
                'children' => [
                    ['id' => $this->ids->create('manual'), 'name' => 'manual'],
                    [
                        'id' => $this->ids->create('group'),
                        'name' => 'group',
                        'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
                        'productStreamId' => $this->ids->get('stream'),
                    ],
                ],
            ]],
        ]], $context);

        $invalidatedTags = [];
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->method('invalidate')
            ->willReturnCallback(static function (array $tags) use (&$invalidatedTags): void {
                $invalidatedTags = $tags;
            });
        $subscriber = new CacheInvalidationSubscriber($cacheInvalidator, static::getContainer()->get(Connection::class), true);

        // a manual category only invalidates its own listing, the listings above it are not affected by it
        $subscriber->invalidateProductListingRouteByCategoryIds(new CategoryIndexerEvent([$this->ids->get('manual')], $context));
        static::assertSame([ProductListingRoute::buildName($this->ids->get('manual'))], $invalidatedTags);

        // a group category also reaches the listings above it, which show its products
        $subscriber->invalidateProductListingRouteByCategoryIds(new CategoryIndexerEvent([$this->ids->get('group')], $context));
        static::assertEqualsCanonicalizing(
            array_map(ProductListingRoute::buildName(...), array_values($this->ids->getList(['group', 'parent', 'root']))),
            $invalidatedTags
        );
    }

    private function insertDefaultPropertyGroup(): void
    {
        $groupRepository = static::getContainer()->get('property_group.repository');

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
            ->property('property-assigned', 'group1');

        static::getContainer()->get('product.repository')->create([$builder->build()], Context::createDefaultContext());
    }
}
