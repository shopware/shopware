<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\ContentSystem\Mapping\CustomFieldMappingCandidateProvider;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomFieldMappingCandidateProvider::class)]
class CustomFieldMappingCandidateProviderTest extends TestCase
{
    public function testOffersConfiguredScalarCustomFieldsForEveryRootSource(): void
    {
        $material = $this->customField('material', CustomFieldTypes::TEXT, [
            'label' => ['en-GB' => 'Material', 'de-DE' => 'Material'],
            'helpText' => ['en-GB' => 'The product material'],
        ]);
        $weight = $this->customField('weight', CustomFieldTypes::FLOAT);

        $provider = new CustomFieldMappingCandidateProvider(
            new StaticEntityRepository([new CustomFieldCollection([$material, $weight])]),
            new TagAwareAdapter(new ArrayAdapter()),
        );

        static::assertTrue($provider->supports('product'));

        $candidates = $provider->provide('product');

        static::assertSame(
            ['product.customFields.material', 'product.customFields.weight'],
            array_column($candidates, 'path')
        );
        static::assertSame('customFields', $candidates[0]->group);
        static::assertSame('string', $candidates[0]->valueType);
        static::assertSame(['en-GB' => 'Material', 'de-DE' => 'Material'], $candidates[0]->labelTranslations);
        static::assertSame(['en-GB' => 'The product material'], $candidates[0]->descriptionTranslations);
        static::assertSame('number', $candidates[1]->valueType);
    }

    public function testPagesThroughBoundedSearchesWithoutTruncatingTheCandidateCatalogue(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context): EntitySearchResult {
                static $expectedOffsets = [0, 250];
                $expectedOffset = array_shift($expectedOffsets);

                static::assertSame(250, $criteria->getLimit());
                static::assertSame($expectedOffset, $criteria->getOffset());

                $offset = $criteria->getOffset() ?? 0;
                $count = $offset === 0 ? 250 : 1;
                $fields = [];

                for ($index = 0; $index < $count; ++$index) {
                    $fields[] = $this->customField('field_' . ($offset + $index), CustomFieldTypes::TEXT);
                }

                $collection = new CustomFieldCollection($fields);

                return new EntitySearchResult('custom_field', $count, $collection, null, $criteria, $context);
            });

        $provider = new CustomFieldMappingCandidateProvider(
            $repository,
            new TagAwareAdapter(new ArrayAdapter()),
        );

        static::assertCount(251, $provider->provide('product'));
    }

    public function testOmitsCustomFieldsWhoseStoredShapeIsNotSupported(): void
    {
        $media = $this->customField('manual', CustomFieldTypes::TEXT, ['customFieldType' => 'media']);
        $multiSelect = $this->customField('audiences', CustomFieldTypes::SELECT, ['componentName' => 'sw-multi-select']);
        $json = $this->customField('payload', CustomFieldTypes::JSON);
        $date = $this->customField('release_date', CustomFieldTypes::DATE);

        $provider = new CustomFieldMappingCandidateProvider(
            new StaticEntityRepository([new CustomFieldCollection([$media, $multiSelect, $json, $date])]),
            new TagAwareAdapter(new ArrayAdapter()),
        );

        static::assertSame([], $provider->provide('product'));
    }

    public function testOnlySupportsEntityRootsWithCustomFieldRelations(): void
    {
        $provider = new CustomFieldMappingCandidateProvider(
            new StaticEntityRepository([]),
            new TagAwareAdapter(new ArrayAdapter()),
        );

        static::assertFalse($provider->supports('header'));
        static::assertFalse($provider->supports('footer'));
        static::assertFalse($provider->supports('none'));
        static::assertSame([], $provider->provide('header'));
    }

    #[DataProvider('rootEntities')]
    public function testReadsOnlyTheNamedTranslatedCustomFieldFromTheLoadedRootEntity(
        string $rootSource,
        ProductEntity|CategoryEntity|LandingPageEntity $entity,
    ): void {
        $provider = new CustomFieldMappingCandidateProvider(
            StaticEntityRepository::of(CustomFieldCollection::class),
            new TagAwareAdapter(new ArrayAdapter()),
        );
        $entity->setCustomFields(['material' => 'Raw value', 'raw_only' => 'Do not expose']);
        $entity->setTranslated(['customFields' => ['material' => 'Leather', 'private' => 'Do not expose']]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), [$rootSource => $entity], [], null);

        $resolved = $provider->resolveRootSource(MappingSourceReference::root($rootSource, 'customFields.material'), $context);

        static::assertTrue($resolved->hasValue);
        static::assertSame('Leather', $resolved->value);
        static::assertTrue($provider->resolveRootSource(MappingSourceReference::root($rootSource, 'customFields.missing'), $context)->isNoValue());
        static::assertTrue($provider->resolveRootSource(MappingSourceReference::root($rootSource, 'customFields.raw_only'), $context)->isNoValue());
    }

    public function testPreservesAnExplicitNullTranslatedCustomField(): void
    {
        $provider = new CustomFieldMappingCandidateProvider(
            StaticEntityRepository::of(CustomFieldCollection::class),
            new TagAwareAdapter(new ArrayAdapter()),
        );
        $product = new ProductEntity();
        $product->setCustomFields(['material' => 'Raw value']);
        $product->setTranslated(['customFields' => ['material' => null]]);
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['product' => $product], [], null);

        $resolved = $provider->resolveRootSource(MappingSourceReference::root('product', 'customFields.material'), $context);

        static::assertTrue($resolved->hasValue);
        static::assertNull($resolved->value);
    }

    public function testReturnsNoValueWithoutTranslatedCustomFields(): void
    {
        $provider = new CustomFieldMappingCandidateProvider(
            StaticEntityRepository::of(CustomFieldCollection::class),
            new TagAwareAdapter(new ArrayAdapter()),
        );
        $product = new ProductEntity();
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['product' => $product], [], null);

        static::assertTrue($provider->resolveRootSource(MappingSourceReference::root('product', 'customFields.material'), $context)->isNoValue());
    }

    /**
     * @return \Generator<string, array{string, ProductEntity|CategoryEntity|LandingPageEntity}>
     */
    public static function rootEntities(): \Generator
    {
        yield 'product' => ['product', new ProductEntity()];
        yield 'category' => ['category', new CategoryEntity()];
        yield 'landing page' => ['landing_page', new LandingPageEntity()];
    }

    public function testCandidateCarriesOnlyItsOwnFieldAndSetCacheTags(): void
    {
        $field = $this->customField('material', CustomFieldTypes::TEXT);
        $setId = Uuid::randomHex();
        $field->setCustomFieldSetId($setId);

        $provider = new CustomFieldMappingCandidateProvider(
            new StaticEntityRepository([new CustomFieldCollection([$field])]),
            new TagAwareAdapter(new ArrayAdapter()),
        );

        $candidate = $provider->provide('product')[0];

        static::assertSame([
            'content-system-custom-field-' . $field->getId(),
            'content-system-custom-field-set-' . $setId,
        ], $candidate->cacheTags);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function customField(string $name, string $type, array $config = []): CustomFieldEntity
    {
        $customField = new CustomFieldEntity();
        $customField->setId(Uuid::randomHex());
        $customField->setCustomFieldSetId(Uuid::randomHex());
        $customField->setName($name);
        $customField->setType($type);
        $customField->setConfig($config);
        $customField->setActive(true);
        $customField->setStoreApiAware(true);

        return $customField;
    }
}
