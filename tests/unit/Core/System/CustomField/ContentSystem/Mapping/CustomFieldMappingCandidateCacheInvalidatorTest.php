<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\ContentSystem\Mapping\CustomFieldMappingCandidateCacheInvalidator;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomFieldMappingCandidateCacheInvalidator::class)]
class CustomFieldMappingCandidateCacheInvalidatorTest extends TestCase
{
    public function testInvalidatesCandidateAndStorefrontCachesWhenCustomFieldsChange(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidate')->with(['custom_field']);
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['custom_field_mapping_candidates']);
        $invalidator = new CustomFieldMappingCandidateCacheInvalidator($cacheInvalidator, $cache);
        $context = Context::createDefaultContext();
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [], $context)]),
            [],
        );

        $invalidator->invalidate($event);
    }

    public function testLeavesCachesAloneForOtherEntityChanges(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');
        $invalidator = new CustomFieldMappingCandidateCacheInvalidator($cacheInvalidator, $cache);
        $context = Context::createDefaultContext();
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent('product', [], $context)]),
            [],
        );

        $invalidator->invalidate($event);
    }
}
