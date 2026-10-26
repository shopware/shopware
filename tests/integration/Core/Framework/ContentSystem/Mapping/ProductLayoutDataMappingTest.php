<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proves the half of data mapping a dotted path cannot reach on its own: provider media reshaping. Both media paths a
 * product page can offer hold assignment records rather than pictures — `product.cover` a single
 * `ProductMediaEntity`, `product.media` a `ProductMediaCollection` of them — while every media element
 * declares `MediaEntity` or `MediaCollection`. Walking from the assignment to the picture is a further hop
 * for the one and a loop for the other, and a path can express neither.
 *
 * These cases assert on what the storefront is served. An unwrapped assignment would still look like a populated
 * media property in a shallow assertion, which is why the collection case pins the ids and their order, and the
 * single case pins that the id is the picture's rather than the assignment's.
 *
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class ProductLayoutDataMappingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const PRODUCT_NAME = 'Trekking backpack';

    private const AUTHORED_TEXT = '<p>Authored copy</p>';

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->browser = $this->createSalesChannelBrowser();
    }

    #[TestDox('serves the bound product name for an inline mapping token in the text property')]
    public function testAMappedTextPropertyServesTheProductValue(): void
    {
        $this->createProduct();
        $this->persistLayout($this->textElement(mappedTo: 'product.name'));

        static::assertSame(self::PRODUCT_NAME, $this->servedProperties()['text'] ?? null);
    }

    #[TestDox('serves a catalogued product custom field in an inline mapping token')]
    public function testAMappedTextPropertyServesTheProductCustomField(): void
    {
        $customFieldName = 'content_system_material_' . Uuid::randomHex();
        $this->createProductCustomField($customFieldName);
        $this->createProduct(customFields: [$customFieldName => 'Leather']);
        $this->persistLayout($this->textElement(mappedTo: 'product.customFields.' . $customFieldName));

        static::assertSame('Leather', $this->servedProperties()['text'] ?? null);
    }

    #[TestDox('serves the picture behind the cover assignment when the media property is mapped to product.cover')]
    public function testAMappedCoverServesTheMedia(): void
    {
        $this->createMedia('cover-image');
        $this->createProduct(coverMedia: 'cover-image');
        $this->persistLayout($this->imageElement(mappedTo: 'product.cover'));

        $media = $this->servedProperties()['media'] ?? null;
        static::assertIsArray($media);
        static::assertSame($this->ids->get('cover-image'), $media['id'] ?? null);
    }

    /**
     * `Sw:Media:Gallery.mediaItems` is a `MediaCollection`; the provider turns product assignments into
     * its media entries.
     *
     * The order assertion is not incidental: the gallery is the storefront's picture order, and the
     * association comes back in no guaranteed order, so the provider sorts by the positions the merchant
     * authored in the product's media tab. The fixture assigns them in the reverse of that order.
     */
    #[TestDox('serves the product images in their authored order when the gallery is mapped to product.media')]
    public function testAMappedMediaCollectionServesTheImagesInPositionOrder(): void
    {
        $this->createMedia('first-image');
        $this->createMedia('second-image');
        $this->createProduct(galleryMedia: ['second-image' => 1, 'first-image' => 0]);
        $this->persistLayout($this->galleryElement(mappedTo: 'product.media'));

        $items = $this->servedProperties()['mediaItems'] ?? null;
        static::assertIsArray($items);

        $servedIds = [];

        foreach (array_values($items) as $item) {
            static::assertIsArray($item);
            $servedIds[] = $item['id'] ?? null;
        }

        static::assertSame([$this->ids->get('first-image'), $this->ids->get('second-image')], $servedIds);
    }

    /**
     * A missing source value falls back to the authored media selection.
     */
    #[TestDox('uses the authored media when the mapped product has no cover')]
    public function testAMappedCoverWithoutAValueUsesTheAuthoredMedia(): void
    {
        $this->createMedia('picked-image');
        $this->createProduct();
        $this->persistLayout($this->imageElement(
            mappedTo: 'product.cover',
            mediaId: $this->ids->get('picked-image'),
        ));

        static::assertSame($this->ids->get('picked-image'), $this->servedProperties()['media']['id'] ?? null);
    }

    /**
     * The `text` property of the shipped text element, optionally carrying an inline mapping token.
     *
     * @return array<string, mixed>
     */
    private function textElement(?string $mappedTo): array
    {
        $element = [
            'id' => $this->ids->create('text'),
            'component' => 'Sw:Content:Text',
            'properties' => ['text' => $mappedTo === null ? self::AUTHORED_TEXT : '{{map:' . $mappedTo . '}}'],
        ];

        return $element;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageElement(string $mappedTo, ?string $mediaId = null): array
    {
        return [
            'id' => $this->ids->create('image'),
            'component' => 'Sw:Media:Image',
            'properties' => $mediaId === null ? [] : ['mediaId' => $mediaId],
            'dataRequirements' => [
                'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            ],
            'acceptsContext' => ['media' => $this->consumer($mappedTo)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function galleryElement(string $mappedTo): array
    {
        return [
            'id' => $this->ids->create('gallery'),
            'component' => 'Sw:Media:Gallery',
            'properties' => [],
            'dataRequirements' => [
                'mediaItems' => ['source' => 'entity_collection', 'config' => ['entity' => 'media', 'property' => 'mediaIds']],
            ],
            'acceptsContext' => ['mediaItems' => $this->consumer($mappedTo, 'collection')],
        ];
    }

    /**
     * A mapping on the wire: root-scoped, keyed by the property it fills, carrying its dotted source path, and
     * optional, which is what lets an unresolvable path fall back rather than fail the page.
     *
     * @return array<string, mixed>
     */
    private function consumer(string $sourcePath, string $type = 'single'): array
    {
        return [
            'type' => $type,
            'required' => false,
            'scope' => 'root',
            'source' => MappingSourceReference::fromRootPath($sourcePath)->jsonSerialize(),
        ];
    }

    /**
     * The served `properties` map of the fixture's single content element.
     *
     * @return array<string, mixed>
     */
    private function servedProperties(): array
    {
        $this->browser->request('GET', '/store-api/content/product/' . $this->ids->get('product'));

        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);

        $properties = $body['elements'][0]['slots']['content'][0]['properties'] ?? null;
        static::assertIsArray($properties, 'The fixture element must be served with a properties map.');

        return $properties;
    }

    /**
     * The page entity is loaded through the sales-channel repository, so the product needs a visibility for
     * the browser's channel or the layout renders against nothing.
     *
     * @param array<string, int> $galleryMedia keyed by media fixture key, valued by authored position
     * @param array<string, mixed> $customFields
     */
    private function createProduct(?string $coverMedia = null, array $galleryMedia = [], array $customFields = []): void
    {
        $payload = [
            'id' => $this->ids->create('product'),
            'name' => self::PRODUCT_NAME,
            'productNumber' => 'data-mapping-product',
            'stock' => 10,
            'active' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'mapping-tax', 'taxRate' => 19],
            'visibilities' => [[
                'salesChannelId' => $this->browser->getServerParameter('test-sales-channel-id'),
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ]],
        ];

        if ($customFields !== []) {
            $payload['customFields'] = $customFields;
        }

        $assignments = [];

        foreach ($galleryMedia as $key => $position) {
            $assignments[] = [
                'id' => $this->ids->create('assignment-' . $key),
                'mediaId' => $this->ids->get($key),
                'position' => $position,
            ];
        }

        if ($coverMedia !== null) {
            $coverAssignmentId = $this->ids->create('cover-assignment');
            $assignments[] = ['id' => $coverAssignmentId, 'mediaId' => $this->ids->get($coverMedia), 'position' => 0];
            $payload['coverId'] = $coverAssignmentId;
        }

        if ($assignments !== []) {
            $payload['media'] = $assignments;
        }

        $this->repository('product.repository')->create([$payload], Context::createDefaultContext());
    }

    private function createProductCustomField(string $name): void
    {
        $this->repository('custom_field_set.repository')->create([[
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
            ]],
        ]], Context::createDefaultContext());
    }

    private function createMedia(string $key): void
    {
        $this->repository('media.repository')->create([[
            'id' => $this->ids->create($key),
            'fileName' => $key,
            'fileExtension' => 'png',
            'mimeType' => 'image/png',
            'path' => 'media/' . $key . '.png',
            'private' => false,
        ]], Context::createDefaultContext());
    }

    /**
     * One grid root holding the given element, assigned to the fixture product.
     *
     * @param array<string, mixed> $element
     */
    private function persistLayout(array $element): void
    {
        $context = Context::createDefaultContext();

        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->create('layout'),
            'name' => 'product-data-mapping',
            'version' => '1.0.0',
            'rootSource' => 'product',
            'layout' => [[
                'id' => $this->ids->create('root-grid'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => ['content' => [$element]],
            ]],
        ]], $context);

        $this->repository('product_content_layout.repository')->create([[
            'id' => $this->ids->create('assignment'),
            'productId' => $this->ids->get('product'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $serviceId): EntityRepository
    {
        $repository = static::getContainer()->get($serviceId);
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
