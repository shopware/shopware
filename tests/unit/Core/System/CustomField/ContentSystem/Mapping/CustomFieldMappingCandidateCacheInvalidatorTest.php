<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\ContentSystem\Mapping\CustomFieldMappingCacheTags;
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
    public function testFieldChangesInvalidateMappingMetadataAndTheCatalogue(): void
    {
        $fieldId = Uuid::randomHex();
        $setId = Uuid::randomHex();
        $invalidator = $this->invalidator([CustomFieldMappingCacheTags::METADATA]);
        $context = Context::createDefaultContext();
        $fieldWrite = new EntityWriteResult(
            $fieldId,
            ['id' => $fieldId, 'customFieldSetId' => $setId],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
        );
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$fieldWrite], $context)]),
            [],
        );

        $invalidator->invalidate($event);
    }

    public function testSetChangesInvalidateMappingMetadataAndTheCatalogue(): void
    {
        $setId = Uuid::randomHex();
        $invalidator = $this->invalidator([CustomFieldMappingCacheTags::METADATA]);
        $context = Context::createDefaultContext();
        $setWrite = new EntityWriteResult($setId, ['id' => $setId], CustomFieldSetDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(CustomFieldSetDefinition::ENTITY_NAME, [$setWrite], $context)]),
            [],
        );

        $invalidator->invalidate($event);
    }

    public function testRelationChangesInvalidateMappingMetadataAndTheCatalogue(): void
    {
        $setId = Uuid::randomHex();
        $invalidator = $this->invalidator([CustomFieldMappingCacheTags::METADATA]);
        $context = Context::createDefaultContext();
        $relationWrite = new EntityWriteResult(
            Uuid::randomHex(),
            ['setId' => $setId],
            CustomFieldSetRelationDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_DELETE,
        );
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(CustomFieldSetRelationDefinition::ENTITY_NAME, [$relationWrite], $context)]),
            [],
        );

        $invalidator->invalidate($event);
    }

    /**
     * @param EntityWriteResult<non-empty-string> $write
     */
    #[DataProvider('mixedEntityWrites')]
    public function testMixedMetadataWritesInvalidatePagesAndCatalogueOnlyOnce(string $entityName, EntityWriteResult $write): void
    {
        $fieldId = Uuid::randomHex();
        $invalidator = $this->invalidator([CustomFieldMappingCacheTags::METADATA]);
        $context = Context::createDefaultContext();
        $fieldWrite = new EntityWriteResult(
            $fieldId,
            ['id' => $fieldId, 'config' => ['label' => ['en-GB' => 'Updated field']]],
            CustomFieldDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
        );
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [$fieldWrite], $context),
                new EntityWrittenEvent($entityName, [$write], $context),
            ]),
            [],
        );

        $invalidator->invalidate($event);
    }

    /**
     * @return \Generator<string, array{string, EntityWriteResult<non-empty-string>}>
     */
    public static function mixedEntityWrites(): \Generator
    {
        $setId = Uuid::randomHex();

        yield 'field update and another set deactivation' => [
            CustomFieldSetDefinition::ENTITY_NAME,
            new EntityWriteResult($setId, ['id' => $setId, 'active' => false], CustomFieldSetDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
        ];
        yield 'field update and another set relation change' => [
            CustomFieldSetRelationDefinition::ENTITY_NAME,
            new EntityWriteResult(Uuid::randomHex(), ['setId' => $setId], CustomFieldSetRelationDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
        ];
    }

    public function testRelationDeletionWithoutSetIdInvalidatesMappedPagesAndTheCatalogue(): void
    {
        $invalidator = $this->invalidator([CustomFieldMappingCacheTags::METADATA]);
        $context = Context::createDefaultContext();
        $relationId = Uuid::randomHex();
        $relationWrite = new EntityWriteResult(
            $relationId,
            ['id' => $relationId],
            CustomFieldSetRelationDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_DELETE,
        );
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(CustomFieldSetRelationDefinition::ENTITY_NAME, [$relationWrite], $context)]),
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
        $context = Context::createDefaultContext();
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent('product', [], $context)]),
            [],
        );

        (new CustomFieldMappingCandidateCacheInvalidator($cacheInvalidator, $cache))->invalidate($event);
    }

    /**
     * @param list<string> $pageTags
     */
    private function invalidator(array $pageTags): CustomFieldMappingCandidateCacheInvalidator
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidate')->with($pageTags);
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['custom_field_mapping_candidates']);

        return new CustomFieldMappingCandidateCacheInvalidator($cacheInvalidator, $cache);
    }
}
