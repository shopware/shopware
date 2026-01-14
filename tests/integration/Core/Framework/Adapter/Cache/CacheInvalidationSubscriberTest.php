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
use Shopware\Core\Framework\Util\BacktraceCollector;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
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
        $this->backtraceCollector = $this->createMock(BacktraceCollector::class);

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
            'info',
            $this->backtraceCollector
        );

        $this->cacheInvalidationSubscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            static::getContainer()->get(Connection::class),
            true
        );
    }

    public function testItInvalidatesCacheIfPropertyGroupIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = static::getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'sortingType' => PropertyGroupDefinition::SORTING_TYPE_POSITION,
            ],
        ], Context::createDefaultContext());

        $this->backtraceCollector->expects($this->once())->method('collect')->willReturn([
            [
                'class' => CacheInvalidationSubscriber::class,
                'function' => 'invalidatePropertyFilters',
            ],
        ]);

        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                'info',
                'Purged 1 tags.',
                static::callback(function (array $context): bool {
                    static::assertCount(1, $context['tags']);
                    static::assertSame('Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber', $context['caller']['class']);
                    static::assertSame('invalidatePropertyFilters', $context['caller']['function']);

                    return true;
                })
            );

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyGroupTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = static::getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'name' => 'new name',
            ],
        ], Context::createDefaultContext());

        $this->backtraceCollector->expects($this->once())->method('collect')->willReturn([
            [
                'class' => CacheInvalidationSubscriber::class,
                'function' => 'invalidateSnippets',
            ],
        ]);

        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                'info',
                'Purged 1 tags.',
                static::callback(function (array $context): bool {
                    static::assertCount(1, $context['tags']);
                    static::assertSame('Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber', $context['caller']['class']);
                    static::assertSame('invalidateSnippets', $context['caller']['function']);

                    return true;
                })
            );

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfPropertyOptionIsAddedToGroup(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = static::getContainer()->get('property_group.repository');
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

        $this->logger->expects($this->never())->method('log');
        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyOptionIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = static::getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-assigned'),
                'colorHexCode' => '#000000',
            ],
        ], Context::createDefaultContext());

        $this->backtraceCollector->expects($this->once())->method('collect')->willReturn([
            [
                'class' => CacheInvalidationSubscriber::class,
                'function' => 'invalidatePropertyFilters',
            ],
        ]);

        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                'info',
                'Purged 1 tags.',
                static::callback(function (array $context): bool {
                    static::assertCount(1, $context['tags']);
                    static::assertSame('Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber', $context['caller']['class']);
                    static::assertSame('invalidatePropertyFilters', $context['caller']['function']);

                    return true;
                })
            );

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfUnassignedPropertyOptionIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = static::getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-unassigned'),
                'colorHexCode' => '#000000',
            ],
        ], Context::createDefaultContext());

        $this->logger->expects($this->never())->method('log');
        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyOptionTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = static::getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-assigned'),
                'name' => 'updated',
            ],
        ], Context::createDefaultContext());

        $this->backtraceCollector->expects($this->once())->method('collect')->willReturn([
            [
                'class' => CacheInvalidationSubscriber::class,
                'function' => 'invalidatePropertyFilters',
            ],
        ]);

        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                'info',
                'Purged 1 tags.',
                static::callback(function (array $context): bool {
                    static::assertCount(1, $context['tags']);
                    static::assertSame('Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber', $context['caller']['class']);
                    static::assertSame('invalidatePropertyFilters', $context['caller']['function']);

                    return true;
                })
            );

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfUnassignedPropertyOptionTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = static::getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-unassigned'),
                'name' => 'updated',
            ],
        ], Context::createDefaultContext());

        $this->logger->expects($this->never())->method('log');
        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfProductIsCreatedWithExistingOption(): void
    {
        $this->insertDefaultPropertyGroup();

        $builder = new ProductBuilder($this->ids, 'product2');
        $builder->price(10)
            ->property('property-assigned', '');

        $event = static::getContainer()->get('product.repository')->create([$builder->build()], Context::createDefaultContext());

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
