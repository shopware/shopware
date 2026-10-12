<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
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
            static::createStub(CacheTagCollector::class),
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

    public function testOmitsCustomFieldsWhoseStoredShapeIsNotSupported(): void
    {
        $media = $this->customField('manual', CustomFieldTypes::TEXT, ['customFieldType' => 'media']);
        $multiSelect = $this->customField('audiences', CustomFieldTypes::SELECT, ['componentName' => 'sw-multi-select']);
        $json = $this->customField('payload', CustomFieldTypes::JSON);
        $date = $this->customField('release_date', CustomFieldTypes::DATE);

        $provider = new CustomFieldMappingCandidateProvider(
            new StaticEntityRepository([new CustomFieldCollection([$media, $multiSelect, $json, $date])]),
            new TagAwareAdapter(new ArrayAdapter()),
            static::createStub(CacheTagCollector::class),
        );

        static::assertSame([], $provider->provide('product'));
    }

    public function testOnlySupportsEntityRootsWithCustomFieldRelations(): void
    {
        $provider = new CustomFieldMappingCandidateProvider(
            new StaticEntityRepository([]),
            new TagAwareAdapter(new ArrayAdapter()),
            static::createStub(CacheTagCollector::class),
        );

        static::assertFalse($provider->supports('header'));
        static::assertFalse($provider->supports('footer'));
        static::assertFalse($provider->supports('none'));
        static::assertSame([], $provider->provide('header'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function customField(string $name, string $type, array $config = []): CustomFieldEntity
    {
        $customField = new CustomFieldEntity();
        $customField->setId(Uuid::randomHex());
        $customField->setName($name);
        $customField->setType($type);
        $customField->setConfig($config);
        $customField->setActive(true);
        $customField->setStoreApiAware(true);

        return $customField;
    }
}
