<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proves data mapping end-to-end on a listing page layout: an author embeds a `category.name` inline token in
 * the `text` property of a shipped `Sw:Content:Text` element, and the Store API serves the bound category's own
 * name in place of the token. Whole-field mapping is covered on the mappable REFERENCE property,
 * `Sw:Media:Image.media` mapped to
 * `category.media`, which differs in one way that matters — the property is required and the element type
 * fills it itself from a picked `mediaId`, so mapping competes with an existing source rather than with a
 * plain authored value.
 *
 * Missing mapped data is omitted from the rendered element, even if an authored value is stored beneath it.
 *
 * A mapping is a root-scoped context consumer keyed by the property it fills and carrying a typed source
 * reference into the page-level data, and the
 * delivered-context tier already outranks the authored tier at `RenderedElementFactory`. What the feature adds
 * on top is the declaration (`mappable: true` in the element type), the curated catalogue of offerable paths,
 * and the write gate that admits only a mapping satisfying both — so those are what the write-rejection cases
 * below pin, while the rendering case pins that the two halves meet.
 *
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class CategoryLayoutDataMappingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const CATEGORY_NAME = 'Outdoor equipment';

    private const AUTHORED_TEXT = '<p>Authored copy</p>';

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->browser = $this->createSalesChannelBrowser();
    }

    #[TestDox('serves the bound category name for an inline mapping token in the text property')]
    public function testAMappedPropertyServesTheEntityValue(): void
    {
        $this->createCategory();
        $this->persistLayout($this->textElement(mappedTo: 'category.name'));

        static::assertSame(self::CATEGORY_NAME, $this->servedText());
    }

    /**
     * The control. Without it, a rendering bug that dropped `properties.text` entirely would still let the
     * mapped case pass by serving the entity value from some other tier.
     */
    #[TestDox('serves the authored copy when the same property carries no mapping')]
    public function testAnUnmappedPropertyServesItsAuthoredValue(): void
    {
        $this->createCategory();
        $this->persistLayout($this->textElement(mappedTo: null));

        static::assertSame(self::AUTHORED_TEXT, $this->servedText());
    }

    /**
     * Inline mapping keeps authored prose alongside the token, so unmapping restores the original authored text.
     */
    #[TestDox('keeps the authored value in storage while the mapping shadows it')]
    public function testMappingShadowsTheAuthoredValueWithoutErasingIt(): void
    {
        $this->createCategory();
        $element = $this->textElement(mappedTo: 'category.name');
        $element['properties']['text'] = 'Authored copy {{map:category.name}}';
        $this->persistLayout($element);

        $stored = $this->rawStoredRoots()[0]['slots']['content'][0];

        static::assertSame('Authored copy {{map:category.name}}', $stored['properties']['text']);
        static::assertArrayNotHasKey('acceptsContext', $stored);
    }

    #[TestDox('rejects a mapping onto a path the category catalogue does not offer')]
    public function testTheWriteGateRejectsAnUncataloguedPath(): void
    {
        $this->createCategory();

        // A real, resolvable category member that the catalogue simply does not vouch for, so the rejection
        // can only come from the allowlist and not from the path failing to resolve.
        $codes = $this->assertLayoutRejected($this->imageElement(mappedTo: 'category.cmsPageId', mediaId: null));

        static::assertSame([ContentSystemException::UNKNOWN_MAPPING_PATH], $codes);
    }

    /**
     * `category.name` is catalogued and resolves fine; it just yields a string where the image's `media`
     * property declares a `MediaEntity`. Only the write gate can catch this — the render path would serve the
     * wrong type.
     */
    #[TestDox('rejects a catalogued path whose value cannot fill the declared property type')]
    public function testTheWriteGateRejectsATypeMismatch(): void
    {
        $this->createCategory();

        $codes = $this->assertLayoutRejected($this->imageElement(mappedTo: 'category.name', mediaId: null));

        static::assertSame([ContentSystemException::MAPPING_TYPE_MISMATCH], $codes);
    }

    /**
     * `Sw:Product:Listing` receives the category page's listing as a root-scoped consumer keyed by the bare
     * data-requirement name `productListing` and aliased onto its declared `listing` property — the shape
     * `Mutation/ContextConsumerMirror` writes for wiring it resolved, not a mapping an author made. The gate
     * once judged it and demanded `mappable: true` on `listing`, which made every listing page unsavable.
     */
    #[TestDox('saves a listing element whose root-scoped wiring is not a mapping')]
    public function testTheWriteGateAdmitsMirroredRootScopedWiring(): void
    {
        $this->createCategory();

        $this->persistLayout([
            'id' => $this->ids->create('listing'),
            'component' => 'Sw:Product:Listing',
            'properties' => [],
            'acceptsContext' => [
                'productListing' => [
                    'type' => 'single',
                    'required' => true,
                    'propertyAlias' => 'listing',
                    'scope' => 'root',
                ],
            ],
        ]);

        $stored = $this->rawStoredRoots()[0]['slots']['content'][0];

        static::assertSame('listing', $stored['acceptsContext']['productListing']['propertyAlias']);
    }

    /**
     * The first reference property to become mappable. Unlike `text`, `Sw:Media:Image.media` is filled by the
     * element type's own `resolvedBy: mediaId` binding, so mapping it is the author choosing the category's
     * image over one they pick by hand — two sources for one property, settled at mint time by the delivered
     * tier outranking the loader.
     */
    #[TestDox('serves the bound category media in place of the picked one when the media property is mapped')]
    public function testAMappedReferenceServesTheEntityValue(): void
    {
        $this->createMedia('category-media');
        $this->createMedia('picked-media');
        $this->createCategory($this->ids->get('category-media'));
        $this->persistLayout($this->imageElement(mappedTo: 'category.media', mediaId: $this->ids->get('picked-media')));

        static::assertSame($this->ids->get('category-media'), $this->servedMediaId());
    }

    /**
     * The control for the case above: the same element, same picked media, no mapping.
     */
    #[TestDox('serves the picked media when the media property carries no mapping')]
    public function testAnUnmappedReferenceServesItsPickedValue(): void
    {
        $this->createMedia('category-media');
        $this->createMedia('picked-media');
        $this->createCategory($this->ids->get('category-media'));
        $this->persistLayout($this->imageElement(mappedTo: null, mediaId: $this->ids->get('picked-media')));

        static::assertSame($this->ids->get('picked-media'), $this->servedMediaId());
    }

    /**
     * An author who maps the image has no reason to also pick a media, but `media` is a required reference and
     * its shipped `resolvedBy: mediaId` binding still resolves Stored, so the resolvability gate would read the
     * empty `mediaId` as an unfilled required input and refuse the save. It must not: the mapping fills the
     * property. This is the one case where making a reference mappable took more than the declaration.
     */
    #[TestDox('saves a mapped image that picks no media of its own')]
    public function testAMappedReferenceNeedsNoPickedValueToSave(): void
    {
        $this->createMedia('category-media');
        $this->createCategory($this->ids->get('category-media'));
        $this->persistLayout($this->imageElement(mappedTo: 'category.media', mediaId: null));

        static::assertSame($this->ids->get('category-media'), $this->servedMediaId());
    }

    /**
     * A missing mapped member omits the property, even when the layout stores an authored value.
     */
    #[TestDox('renders an empty text value when the inline mapping source member is unavailable')]
    public function testInlineMappingPathResolvingToNullRendersAnEmptyTextValue(): void
    {
        $this->createCategory();
        $this->persistLayout($this->textElement(mappedTo: 'category.description'));

        static::assertSame('', $this->servedProperties()['text'] ?? null);
    }

    /**
     * A missing mapped entity value also omits the property rather than exposing an authored selection.
     */
    #[TestDox('omits the mapped reference when the category has no media')]
    public function testAMappedReferenceResolvingToNullOmitsTheProperty(): void
    {
        $this->createMedia('picked-media');
        $this->createCategory();
        $this->persistLayout($this->imageElement(mappedTo: 'category.media', mediaId: $this->ids->get('picked-media')));

        static::assertArrayNotHasKey('media', $this->servedProperties());
    }

    /**
     * With no category media and no picked id, the optional image property is omitted from the response.
     */
    #[TestDox('omits the image property when neither the mapped category nor a picked media supplies one')]
    public function testMappedReferenceIsOmittedWhenNeitherSourceProvidesAnImage(): void
    {
        $this->createCategory();
        $this->persistLayout($this->imageElement(mappedTo: 'category.media', mediaId: null));

        static::assertArrayNotHasKey('media', $this->servedProperties());
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
     * The shipped image element, optionally carrying a mapping and optionally picking a media of its own. The
     * `dataRequirements` entry is the type default the mutation layer attaches on insert, written out here
     * because this fixture goes through the DAL rather than the mutation pipeline.
     *
     * @return array<string, mixed>
     */
    private function imageElement(?string $mappedTo, ?string $mediaId): array
    {
        $element = [
            'id' => $this->ids->create('image'),
            'component' => 'Sw:Media:Image',
            'properties' => $mediaId === null ? [] : ['mediaId' => $mediaId],
            'dataRequirements' => [
                'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            ],
        ];

        if ($mappedTo !== null) {
            $element['acceptsContext'] = [
                'media' => [
                    'type' => 'single',
                    'required' => false,
                    'scope' => 'root',
                    'source' => MappingSourceReference::fromRootPath($mappedTo)->jsonSerialize(),
                ],
            ];
        }

        return $element;
    }

    /**
     * The served `properties` map of the fixture's single content element. Returned whole rather than as one
     * value, because the fallback cases below assert that a key is ABSENT, which a per-key reader cannot say.
     *
     * @return array<string, mixed>
     */
    private function servedProperties(): array
    {
        $this->browser->request('GET', '/store-api/content/category/' . $this->ids->get('category'));

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
     * The served `properties.text` of the fixture's text element.
     */
    private function servedText(): string
    {
        $text = $this->servedProperties()['text'] ?? null;
        static::assertIsString($text, 'The fixture text element must be served with a text property.');

        return $text;
    }

    /**
     * The id of the served `properties.media` of the fixture's image element.
     */
    private function servedMediaId(): string
    {
        $media = $this->servedProperties()['media'] ?? null;
        static::assertIsArray($media, 'The fixture image element must be served with a media property.');
        static::assertIsString($media['id'] ?? null);

        return $media['id'];
    }

    /**
     * Writes the layout, expects the write gate to reject it, and returns the codes it raised.
     *
     * @param array<string, mixed> $element
     *
     * @return list<string>
     */
    private function assertLayoutRejected(array $element): array
    {
        try {
            $this->persistLayout($element);
        } catch (WriteException $exception) {
            $codes = [];

            foreach ($exception->getExceptions() as $inner) {
                if (!$inner instanceof WriteConstraintViolationException) {
                    continue;
                }

                foreach ($inner->getViolations() as $violation) {
                    $codes[] = (string) $violation->getCode();
                }
            }

            return $codes;
        }

        static::fail('Expected the content layout write gate to reject the mapping.');
    }

    private function createCategory(?string $mediaId = null): void
    {
        $payload = [
            'id' => $this->ids->create('category'),
            'name' => self::CATEGORY_NAME,
            'active' => true,
        ];

        if ($mediaId !== null) {
            $payload['mediaId'] = $mediaId;
        }

        $this->repository('category.repository')->create([$payload], Context::createDefaultContext());
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
     * One grid root holding the given element, assigned to the fixture category. The root is a container
     * because the shipped text element is authored inside one; the mapping under test sits on the child.
     *
     * @param array<string, mixed> $element
     */
    private function persistLayout(array $element): void
    {
        $context = Context::createDefaultContext();

        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->create('layout'),
            'name' => 'category-data-mapping',
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [[
                'id' => $this->ids->create('root-grid'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => ['content' => [$element]],
            ]],
        ]], $context);

        $this->repository('category_content_layout.repository')->create([[
            'id' => $this->ids->create('assignment'),
            'categoryId' => $this->ids->get('category'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    /**
     * The persisted `layout` column, read raw rather than through the DAL, so the assertion over it speaks
     * about what was stored rather than about what a decode would reconstruct.
     *
     * @return list<array<string, mixed>>
     */
    private function rawStoredRoots(): array
    {
        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $raw = $connection->fetchOne(
            'SELECT `layout` FROM `content_layout` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('layout'))]
        );
        static::assertIsString($raw);

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return array_values($decoded);
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
