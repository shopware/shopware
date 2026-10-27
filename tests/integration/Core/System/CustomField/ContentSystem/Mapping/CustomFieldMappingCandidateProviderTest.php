<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomField\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\ContentSystem\Mapping\CustomFieldMappingCandidateProvider;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldMappingCandidateProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CustomFieldMappingCandidateProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = static::getContainer()->get(CustomFieldMappingCandidateProvider::class);
    }

    public function testOffersOnlyFieldsAssignedToTheRequestedRootSource(): void
    {
        $name = 'content_system_material_' . Uuid::randomHex();
        $privateName = 'content_system_private_' . Uuid::randomHex();

        $this->customFieldSetRepository()->create([[
            'id' => Uuid::randomHex(),
            'name' => 'content_system_mapping_' . Uuid::randomHex(),
            'active' => true,
            'global' => false,
            'relations' => [[
                'id' => Uuid::randomHex(),
                'entityName' => 'product',
            ]],
            'customFields' => [[
                'id' => Uuid::randomHex(),
                'name' => $name,
                'type' => 'text',
                'active' => true,
                'storeApiAware' => true,
                'config' => [
                    'label' => ['en-GB' => 'Material'],
                    'helpText' => ['en-GB' => 'The product material'],
                ],
            ], [
                'id' => Uuid::randomHex(),
                'name' => $privateName,
                'type' => 'text',
                'active' => true,
                'storeApiAware' => false,
            ]],
        ]], Context::createDefaultContext());

        $productCandidates = $this->provider->provide('product');
        $categoryCandidates = $this->provider->provide('category');

        static::assertContains('product.customFields.' . $name, array_column($productCandidates, 'path'));
        static::assertNotContains('product.customFields.' . $privateName, array_column($productCandidates, 'path'));
        static::assertNotContains('category.customFields.' . $name, array_column($categoryCandidates, 'path'));
    }

    /**
     * @param array<string, mixed> $requiredFields
     */
    #[DataProvider('rootSources')]
    public function testReadsCustomFieldsFromTheFallbackLanguage(string $rootSource, array $requiredFields): void
    {
        $context = Context::createDefaultContext();
        $languageId = Uuid::randomHex();
        static::getContainer()->get('language.repository')->create([[
            'id' => $languageId,
            'name' => 'Mapping language',
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'localeId' => $this->getLocaleIdOfSystemLanguage(),
        ]], $context);

        $name = $this->createCustomField($rootSource);
        $entityId = Uuid::randomHex();
        $repository = static::getContainer()->get($rootSource . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);
        $repository->create([[
            ...$requiredFields,
            'id' => $entityId,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'Fallback entity',
                    'customFields' => [$name => 'Leather'],
                    ...($rootSource === 'landing_page' ? ['url' => 'fallback-entity'] : []),
                ],
                $languageId => [
                    'name' => 'Translated entity',
                    ...($rootSource === 'landing_page' ? ['url' => 'translated-entity'] : []),
                ],
            ],
        ]], $context);

        $readContext = new Context($context->getSource(), languageIdChain: [$languageId, Defaults::LANGUAGE_SYSTEM]);
        $entity = $repository->search(new Criteria([$entityId]), $readContext)->getEntities()->first();
        static::assertNotNull($entity);
        static::assertNull($entity->get('customFields'));
        static::assertSame([$name => 'Leather'], $entity->getTranslation('customFields'));

        $resolved = $this->provider->resolveRootSource(
            MappingSourceReference::root($rootSource, 'customFields.' . $name),
            new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Content:Text'), [$rootSource => $entity], [], null),
        );

        static::assertTrue($resolved->hasValue);
        static::assertSame('Leather', $resolved->value);
    }

    public function testReadsInheritedProductCustomFields(): void
    {
        $context = Context::createDefaultContext();
        $name = $this->createCustomField('product');
        $variantId = Uuid::randomHex();
        $repository = static::getContainer()->get('product.repository');
        $repository->create([[
            'id' => Uuid::randomHex(),
            'name' => 'Parent product',
            'productNumber' => 'mapping-parent',
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'mapping-tax', 'taxRate' => 19],
            'customFields' => [$name => 'Leather'],
            'children' => [[
                'id' => $variantId,
                'productNumber' => 'mapping-variant',
                'stock' => 10,
            ]],
        ]], $context);

        $context->setConsiderInheritance(true);
        $variant = $repository->search(new Criteria([$variantId]), $context)->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $variant);
        static::assertSame([$name => 'Leather'], $variant->getTranslation('customFields'));

        $resolved = $this->provider->resolveRootSource(
            MappingSourceReference::root('product', 'customFields.' . $name),
            new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Content:Text'), ['product' => $variant], [], null),
        );

        static::assertTrue($resolved->hasValue);
        static::assertSame('Leather', $resolved->value);
    }

    /**
     * @return \Generator<string, array{string, array<string, mixed>}>
     */
    public static function rootSources(): \Generator
    {
        yield 'product' => ['product', [
            'productNumber' => 'mapping-fallback',
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'mapping-tax', 'taxRate' => 19],
        ]];
        yield 'category' => ['category', []];
        yield 'landing page' => ['landing_page', ['salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]]]];
    }

    private function createCustomField(string $rootSource): string
    {
        $name = 'content_system_material_' . Uuid::randomHex();
        $this->customFieldSetRepository()->create([[
            'name' => 'content_system_mapping_' . Uuid::randomHex(),
            'relations' => [['entityName' => $rootSource]],
            'customFields' => [[
                'name' => $name,
                'type' => 'text',
                'active' => true,
                'storeApiAware' => true,
            ]],
        ]], Context::createDefaultContext());

        return $name;
    }

    /**
     * @return EntityRepository<CustomFieldSetCollection>
     */
    private function customFieldSetRepository(): EntityRepository
    {
        return static::getContainer()->get('custom_field_set.repository');
    }
}
