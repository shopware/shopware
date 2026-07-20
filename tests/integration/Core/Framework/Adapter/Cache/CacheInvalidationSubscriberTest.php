<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\Framework\Context;
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
            [$this->createMock(TagAwareAdapterInterface::class)],
            $this->createMock(RedisInvalidatorStorage::class),
            new EventDispatcher(),
            $this->logger,
            new RequestStack([new Request()]),
            $this->createMock(TagAwareAdapterInterface::class),
            false,
            false,
            true,
            $this->backtraceCollector,
            new NativeClock(),
        );

        $this->cacheInvalidationSubscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            static::getContainer()->get(Connection::class),
            true
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
