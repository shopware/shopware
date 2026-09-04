<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\CachedContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the Store-API rendering behaviour of a persisted `content_layout` served through the real
 * `ContentRoute` wiring, over the four output formats the route compiler pass registers.
 *
 * The fixture is one nested tree (grid > [text, grid > image]) assigned to a category, whose root consumes
 * the page-level context the category root source declares, so the same layout drives the structural, style,
 * skeleton-parity, decomposed/data body, field-selection, PWA-composition, partial-render and virtual-root
 * cases. Deliberately not pinned here: the property set an element carries, the ref-id grammar of the
 * decomposed/data formats (their assignment-to-data referential integrity is pinned, no literal ref id is),
 * and what a loader that finds nothing writes.
 *
 * One state outside the request is pinned too: the element-type registry's `cache.system` entry, the backing
 * cache both the render step and the index build read their type specifications through.
 * `CachedContentSystemElementTypeRegistry` populates that entry on its first lookup in a request, and every
 * later lookup in the same request — including the index build, which always runs after rendering's own
 * lookup — reads the populated entry, so a cold render and a warm render are never observably different at the
 * index and this file makes no such comparison. What is pinned instead: a render leaves the entry populated,
 * and the served index's primitive-key membership and emission order come from whatever specification the
 * entry currently holds.
 *
 * Two step orderings inside the rendering pipeline are pinned by outcome rather than by declaration:
 * redistribute wiring validation judging a subtree the partial-render prune would discard, and the page-level
 * context arriving at a root only because the virtual root wraps and then unwraps around hydration.
 *
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class ContentRouteRenderingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const TEXT_VALUE = 'Alpha copy';

    private const LAYOUT_NAME = 'content-route-rendering';

    private const LAYOUT_VERSION = '1.0.0';

    private const MEDIA_FILE_NAME = 'content-route-probe';

    private const MEDIA_PATH = 'media/content-route-probe.png';

    /**
     * The page-level context key the category root source declares, consumed by the root grid of the nested
     * fixture through `PAGE_CONTEXT_PROPERTY`.
     */
    private const PAGE_CONTEXT_KEY = 'category.id';

    private const PAGE_CONTEXT_PROPERTY = 'pageCategoryId';

    /**
     * The undotted, unaliased context key of the seo fixture's root, and therefore also the rendered property
     * name the whole `CategoryEntity` arrives under.
     */
    private const SEO_CONTEXT_PROPERTY = 'category';

    /**
     * The `cache.system` key `CachedContentSystemElementTypeRegistry` stores its specification map under —
     * the backing cache both the render step and the index build read the element types through.
     *
     * @see CachedContentSystemElementTypeRegistry
     */
    private const ELEMENT_TYPE_CACHE_KEY = 'content_system.element_types';

    private IdsCollection $ids;

    private KernelBrowser $browser;

    private ?\Closure $finalizationListener = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->browser = $this->createSalesChannelBrowser();
    }

    /**
     * The kernel outlives the test case, so a listener left on its dispatcher would rewrite the rendered
     * forest of every later test in the process.
     */
    protected function tearDown(): void
    {
        if ($this->finalizationListener !== null) {
            $this->eventDispatcher()->removeListener(RenderedTreeFinalizationEvent::class, $this->finalizationListener);
            $this->finalizationListener = null;
        }

        parent::tearDown();
    }

    #[TestDox('serves the stored element ids, components, slot names and slot nesting in the full format')]
    public function testFullFormatServesTreeStructure(): void
    {
        $this->createNestedLayout();

        $roots = $this->rootElements($this->requestJson($this->uri('content')));
        static::assertCount(1, $roots);

        $root = $roots[0];
        static::assertSame($this->ids->get('root-grid'), $root['id']);
        static::assertSame('Sw:Grid:Container', $root['component']);
        static::assertSame(['content'], array_keys($this->slots($root)));

        $children = $this->slotChildren($root, 'content');
        static::assertCount(2, $children);
        static::assertSame($this->ids->get('text'), $children[0]['id']);
        static::assertSame('Sw:Content:Text', $children[0]['component']);
        static::assertSame([], $this->slots($children[0]), 'The text leaf must carry no slots.');
        static::assertSame($this->ids->get('inner-grid'), $children[1]['id']);
        static::assertSame('Sw:Grid:Container', $children[1]['component']);
        static::assertSame(['content'], array_keys($this->slots($children[1])));

        $grandChildren = $this->slotChildren($children[1], 'content');
        static::assertCount(1, $grandChildren);
        static::assertSame($this->ids->get('image'), $grandChildren[0]['id']);
        static::assertSame('Sw:Media:Image', $grandChildren[0]['component']);
        static::assertSame([], $this->slots($grandChildren[0]), 'The image leaf must carry no slots.');
    }

    #[TestDox('serves a full-format body whose elements are the same forest the in-process consumer reads')]
    public function testFullFormatBodyAndInProcessPageDescribeTheSameForest(): void
    {
        $this->createNestedLayout();

        $bodyIds = array_column($this->flatten($this->rootElements($this->requestJson($this->uri('content')))), 'id');

        // The public alias resolves to the main section's full-format route — the same service the Storefront
        // consumes in process.
        $response = static::getContainer()->get(AbstractContentRoute::class)->load(
            'category/' . $this->ids->get('category'),
            new Request(),
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
        );

        static::assertInstanceOf(ContentRouteResponse::class, $response);

        // The body and the in-process page are two views of the one rendered forest, so a divergence here is
        // an encoding difference rather than two models drifting apart.
        static::assertSame($bodyIds, $this->contentElementIds($response->getContentPage()->elements));
        static::assertSame(
            array_column($this->rootElements($this->requestJson($this->uri('content'))), 'id'),
            array_map(static fn (RenderedElement $element): string => $element->id, $response->getRenderResult()->tree),
        );
    }

    #[TestDox('names the skeleton page keys id, name and version, matching the full format vocabulary')]
    public function testSkeletonNamesThePageTripleWithoutTheLayoutPrefix(): void
    {
        $this->createNestedLayout();

        $body = $this->requestJson($this->uri('content-skeleton'));

        static::assertSame($this->ids->get('layout'), $body['id'] ?? null);
        static::assertSame(self::LAYOUT_NAME, $body['name'] ?? null);
        static::assertSame(self::LAYOUT_VERSION, $body['version'] ?? null);
        static::assertArrayNotHasKey('layoutId', $body);
        static::assertArrayNotHasKey('layoutName', $body);
        static::assertArrayNotHasKey('layoutVersion', $body);

        // Same page vocabulary as the full format, which moved to these names with its own encoder.
        $full = $this->requestJson($this->uri('content'));
        static::assertSame($full['id'], $body['id']);
        static::assertSame($full['name'], $body['name']);
        static::assertSame($full['version'], $body['version']);
    }

    #[TestDox('serves a skeleton that is structurally identical to the full format apart from element properties')]
    public function testSkeletonIsFullMinusProperties(): void
    {
        $this->createNestedLayout();

        $full = $this->rootElements($this->requestJson($this->uri('content')));
        $skeleton = $this->rootElements($this->requestJson($this->uri('content-skeleton')));

        // Proves the full response really did carry properties, so the projection below is a reduction and
        // not a comparison of two shapes that were already empty.
        static::assertArrayHasKey('properties', $full[0]);
        static::assertNotSame([], $full[0]['properties']);

        static::assertSame(
            array_map($this->structuralProjection(...), $full),
            array_map($this->structuralProjection(...), $skeleton),
        );

        foreach ($this->flatten($skeleton) as $element) {
            static::assertArrayNotHasKey('properties', $element);
        }
    }

    #[TestDox('distributes the page-level context of the root source to a consuming layout root')]
    public function testPageLevelContextReachesTheConsumingRoot(): void
    {
        $this->createNestedLayout();

        // The wrap only happens because the root source declares page data requirements, and it is only
        // observable because the stored root really consumes one of them.
        static::assertContains('category', $this->pageDataRequirementKeys());
        $this->assertStoredPageContextConsumer();

        $root = $this->rootElements($this->requestJson($this->uri('content')))[0];

        static::assertArrayHasKey('properties', $root);
        static::assertIsArray($root['properties']);
        static::assertSame(
            $this->ids->get('category'),
            $root['properties'][self::PAGE_CONTEXT_PROPERTY] ?? null,
            'The page-level context must reach the consuming root through the virtual-root wrap/unwrap lifecycle.',
        );
    }

    #[TestDox('returns only the addressed subtree when elementId names a nested element')]
    public function testPartialRenderExtractsSubtree(): void
    {
        $this->createNestedLayout();

        $fullRoots = $this->rootElements($this->requestJson($this->uri('content')));
        static::assertNotContains($this->ids->get('inner-grid'), array_column($fullRoots, 'id'));

        $partialRoots = $this->rootElements($this->requestJson($this->uri('content') . '?elementId=' . $this->ids->get('inner-grid')));

        static::assertCount(1, $partialRoots);
        static::assertSame($this->ids->get('inner-grid'), $partialRoots[0]['id']);
        static::assertSame(['content'], array_keys($this->slots($partialRoots[0])));
        static::assertSame(
            [$this->ids->get('image')],
            array_column($this->slotChildren($partialRoots[0], 'content'), 'id'),
        );
        // The complete flattened id list, so a descendant leaking in through any slot is a failure.
        static::assertSame(
            [$this->ids->get('inner-grid'), $this->ids->get('image')],
            array_column($this->flatten($partialRoots), 'id'),
        );
    }

    #[TestDox('serves decomposed skeletons structurally identical to the skeleton format')]
    public function testDecomposedSkeletonsMatchSkeletonFormat(): void
    {
        $this->createNestedLayout();

        $skeletons = $this->decomposedSkeletons($this->requestJson($this->uri('content-decomposed')));

        // Both sides being empty would satisfy the comparison below, so the decomposed side is proven to
        // carry the fixture's root before it is compared to anything.
        static::assertNotSame([], $skeletons);
        static::assertSame([$this->ids->get('root-grid')], array_column($skeletons, 'id'));

        static::assertSame(
            array_map($this->structuralProjection(...), $this->rootElements($this->requestJson($this->uri('content-skeleton')))),
            array_map($this->structuralProjection(...), $skeletons),
        );
    }

    #[TestDox('serves the decomposed body under its own page alias with the six documented top-level keys')]
    public function testDecomposedFormatServesTheDocumentedTopLevelKeys(): void
    {
        $this->createNestedLayout();

        $body = $this->requestJson($this->uri('content-decomposed'));

        // The module writes the six; the framework appends `apiAlias` to whatever the carrier hands it, which
        // is why the alias is asserted as the last key rather than as one of the encoder's own.
        static::assertSame(
            ['id', 'name', 'version', 'skeletons', 'data', 'assignments', 'apiAlias'],
            array_keys($body),
        );
        static::assertSame($this->ids->get('layout'), $body['id']);
        static::assertSame(self::LAYOUT_NAME, $body['name']);
        static::assertSame(self::LAYOUT_VERSION, $body['version']);
        static::assertSame('content_decomposed_page', $body['apiAlias']);
    }

    #[TestDox('serves the data body under its own page alias with the five documented top-level keys')]
    public function testDataFormatServesTheDocumentedTopLevelKeys(): void
    {
        $this->createNestedLayout();

        $body = $this->requestJson($this->uri('content-data'));

        static::assertSame(
            ['id', 'name', 'version', 'data', 'assignments', 'apiAlias'],
            array_keys($body),
        );
        static::assertSame($this->ids->get('layout'), $body['id']);
        static::assertSame(self::LAYOUT_NAME, $body['name']);
        static::assertSame(self::LAYOUT_VERSION, $body['version']);
        static::assertSame('content_data_page', $body['apiAlias']);
    }

    #[TestDox('serves the data format as the decomposed format without the skeletons')]
    public function testDataFormatIsDecomposedWithoutSkeletons(): void
    {
        $this->createNestedLayout();

        $decomposed = $this->requestJson($this->uri('content-decomposed'));
        $data = $this->requestJson($this->uri('content-data'));

        static::assertArrayNotHasKey('skeletons', $data);
        static::assertSame($decomposed['id'] ?? null, $data['id'] ?? null);
        static::assertSame(self::LAYOUT_NAME, $data['name'] ?? null);
        static::assertSame($decomposed['name'], $data['name']);
        static::assertSame(self::LAYOUT_VERSION, $data['version'] ?? null);
        static::assertSame($decomposed['version'], $data['version']);

        $decomposedAssignments = $this->assignments($decomposed);
        $dataAssignments = $this->assignments($data);
        $decomposedValues = $this->dataMap($decomposed);
        $dataValues = $this->dataMap($data);

        // The fixture really is decomposed on both formats, so the per-property comparison below exercises
        // populated assignment maps rather than two empty ones agreeing vacuously.
        static::assertNotSame([], $decomposedAssignments);

        // Refs are response-local (see the ResolvedValueIndex class docblock) and are not compared here. The
        // element-id/property-key grammar is not a ref and is compared directly; each side's own ref is then
        // resolved through that side's own data map, so only the resulting VALUES are compared across formats.
        static::assertEqualsCanonicalizing(
            array_keys($decomposedAssignments),
            array_keys($dataAssignments),
            'The two formats must assign to the same set of elements.',
        );

        foreach ($decomposedAssignments as $elementId => $propertyMap) {
            static::assertArrayHasKey($elementId, $dataAssignments);
            static::assertEqualsCanonicalizing(
                array_keys($propertyMap),
                array_keys($dataAssignments[$elementId]),
                'Element ' . $elementId . ' must have the same assigned property set in both formats.',
            );

            foreach ($propertyMap as $propertyKey => $decomposedRef) {
                $dataRef = $dataAssignments[$elementId][$propertyKey];

                static::assertArrayHasKey($decomposedRef, $decomposedValues);
                static::assertArrayHasKey($dataRef, $dataValues);

                // assertEquals, not assertSame: the values are keyed maps, and a JSON map's key order is not
                // part of the behaviour under test (MySQL reorders JSON object keys, MariaDB does not).
                static::assertEquals(
                    $decomposedValues[$decomposedRef],
                    $dataValues[$dataRef],
                    'Element ' . $elementId . ' property ' . $propertyKey . ': the value reached through the '
                    . 'decomposed response\'s ref differs from the value reached through the data response\'s ref.',
                );
            }
        }

        // The data map really carries values, not just keys: a map whose entries were all replaced by null
        // would keep every key and every assignment intact.
        $textRefId = $dataAssignments[$this->ids->get('text')]['text'] ?? null;
        static::assertIsString($textRefId);
        static::assertSame(self::TEXT_VALUE, $dataValues[$textRefId] ?? null);
    }

    #[TestDox('enriches a seo-aware entity on a rendered element property when the seo-url header is set')]
    public function testSeoUrlEnrichmentReachesAnEntityOnARenderedProperty(): void
    {
        $this->createSeoAwareCategoryLayout();

        // Fixture guard: a canonical seo url for this category really exists, so `StoreApiSeoResolver::enrich()`
        // would have something to write. Without it the un-enriched body below would prove nothing.
        static::assertSame([$this->ids->get('seo-url')], $this->storedCanonicalSeoUrlIds());

        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $root = $this->rootElements($this->requestJson($this->uri('content')))[0];

        // Presence first: the seo-aware entity really is on the property the absence below is asserted over.
        static::assertIsArray($root['properties']);
        static::assertArrayHasKey(self::SEO_CONTEXT_PROPERTY, $root['properties']);
        $category = $root['properties'][self::SEO_CONTEXT_PROPERTY];
        static::assertIsArray($category);
        static::assertSame($this->ids->get('category'), $category['id'] ?? null);
        static::assertArrayHasKey('seoUrls', $category);

        // `RenderedElement` is not a `Struct`, so `getVars()` cannot walk it; `StoreApiSeoResolver` reaches
        // this entity only through its own by-shape descent over `properties` and `slots`. A null here means
        // that descent no longer runs and the association stays at the null the hydration left.
        static::assertIsArray($category['seoUrls']);
        static::assertCount(1, $category['seoUrls']);
        static::assertSame($this->ids->get('seo-url'), $category['seoUrls'][0]['id'] ?? null);
        static::assertSame($this->ids->get('category'), $category['seoUrls'][0]['foreignKey'] ?? null);
    }

    #[TestDox('carries the stored element style of a root and of a nested element through to the full-format response')]
    public function testFullFormatCarriesElementStyle(): void
    {
        $this->createNestedLayout();
        $this->assertStoredStyleIsPresent();

        $root = $this->rootElements($this->requestJson($this->uri('content')))[0];

        static::assertArrayHasKey('style', $root);
        static::assertIsArray($root['style']);
        static::assertSame(['col-span'], array_keys($root['style']));
        static::assertSame(['xs' => 6], $root['style']['col-span']);

        $nested = $this->slotChildren($root, 'content')[1];

        static::assertArrayHasKey('style', $nested);
        static::assertIsArray($nested['style']);
        static::assertSame(['col-span'], array_keys($nested['style']));
        static::assertSame(['md' => 4], $nested['style']['col-span']);
    }

    #[TestDox('names the full-format page keys id, name and version rather than the layout-prefixed struct names')]
    public function testFullFormatNamesThePageTripleWithoutTheLayoutPrefix(): void
    {
        $this->createNestedLayout();

        $body = $this->requestJson($this->uri('content'));

        static::assertSame($this->ids->get('layout'), $body['id'] ?? null);
        static::assertSame(self::LAYOUT_NAME, $body['name'] ?? null);
        static::assertSame(self::LAYOUT_VERSION, $body['version'] ?? null);
        static::assertArrayNotHasKey('layoutId', $body);
        static::assertArrayNotHasKey('layoutName', $body);
        static::assertArrayNotHasKey('layoutVersion', $body);
        static::assertSame('content_page', $body['apiAlias'] ?? null);
    }

    #[TestDox('carries the element api alias on every full-format node at every depth')]
    public function testFullFormatCarriesTheElementAliasAtEveryDepth(): void
    {
        $this->createNestedLayout();

        $elements = $this->flatten($this->rootElements($this->requestJson($this->uri('content'))));

        // The fixture is three levels deep, so this covers a root, a child and a grandchild.
        static::assertCount(4, $elements);
        foreach ($elements as $element) {
            static::assertSame('content_element', $element['apiAlias'] ?? null, 'Element ' . ($element['id'] ?? '?') . ' carries no element alias.');
        }
    }

    #[TestDox('serves every full-format slot as a JSON array of child elements, with no slot-content wrapper')]
    public function testFullFormatServesSlotsAsPlainArrays(): void
    {
        $this->createNestedLayout();

        $root = $this->rootElements($this->requestJson($this->uri('content')))[0];

        static::assertIsArray($root['slots']['content'] ?? null);
        static::assertTrue(array_is_list($root['slots']['content']), 'A multi-child slot must serialize as a JSON array, not as a numerically keyed object.');
        static::assertArrayNotHasKey('apiAlias', $root['slots']['content'], 'The slot-content wrapper must not appear between an element and its children.');

        $nested = $root['slots']['content'][1];
        static::assertIsArray($nested['slots']['content'] ?? null);
        static::assertTrue(array_is_list($nested['slots']['content']), 'The same holds at the next depth.');
        static::assertArrayNotHasKey('apiAlias', $nested['slots']['content']);
    }

    #[TestDox('drops the authoring-only element keys from the full format')]
    public function testFullFormatOmitsAuthoringOnlyKeys(): void
    {
        $this->createNestedLayout();
        // The fixture authors both of the keys asserted away below: `acceptsContext` on the root and a
        // `dataRequirements` entry on the image, so their absence is a change and not an empty case.
        $this->assertStoredPageContextConsumer();
        static::assertNotSame([], $this->storedImageDataRequirements());

        foreach ($this->flatten($this->rootElements($this->requestJson($this->uri('content')))) as $element) {
            static::assertArrayNotHasKey('dataRequirements', $element);
            static::assertArrayNotHasKey('acceptsContext', $element);
            static::assertArrayNotHasKey('providesContext', $element);
        }
    }

    #[TestDox('filters the protected fields of a hydrated entity out of a full-format property')]
    public function testFullFormatFiltersProtectedEntityFields(): void
    {
        $this->createNestedLayout();

        $media = $this->fullFormatMediaPayload();

        // Present: the payload really is the loaded entity, so the absences below are the protection gate
        // running rather than an empty or stubbed value.
        static::assertSame($this->ids->get('media'), $media['id'] ?? null);
        static::assertSame(self::MEDIA_FILE_NAME, $media['fileName'] ?? null);
        static::assertSame('media', $media['apiAlias'] ?? null);

        // Absent: the non-ApiAware internals that reach this body today. `thumbnailsRo` is the sharpest of
        // them — a PHP-serialized MediaThumbnailCollection string, published verbatim before this encoder.
        static::assertArrayNotHasKey('_uniqueIdentifier', $media);
        static::assertArrayNotHasKey('thumbnailsRo', $media);
        static::assertArrayNotHasKey('versionId', $media);
        static::assertArrayNotHasKey('userId', $media);
        static::assertArrayNotHasKey('fileHash', $media);
        static::assertArrayNotHasKey('productMedia', $media);

        // `createdAt`, `updatedAt` and `path` are ApiAware on media and legitimately survive the gate: this
        // test pins what protection removes, not everything an entity may say.
        static::assertArrayHasKey('createdAt', $media);
    }

    #[TestDox('carries the media entity\'s encoded url on a full-format property')]
    public function testFullFormatCarriesMediaUrl(): void
    {
        $this->createNestedLayout();

        $expectedUrl = $this->independentlyLoadedMediaUrl();
        // The fixture media is public with a path, so the DAL's own runtime computation of `url` really
        // produced a value; a same-empty-string comparison below would otherwise pass vacuously.
        static::assertNotSame('', $expectedUrl);

        $media = $this->fullFormatMediaPayload();

        static::assertArrayHasKey('url', $media);
        static::assertSame($expectedUrl, $media['url']);
    }

    #[TestDox('resolves every decomposed assignment to a known element and to an entry in the data map')]
    public function testDecomposedAssignmentsAreReferentiallyIntact(): void
    {
        $this->createNestedLayout();

        $decomposed = $this->requestJson($this->uri('content-decomposed'));
        $assignments = $this->assignments($decomposed);
        $data = $this->dataMap($decomposed);

        // The fixture really is decomposed: the properties left the elements and became assignments.
        static::assertNotSame([], $assignments);

        $knownIds = array_column($this->flatten($this->rootElements($this->requestJson($this->uri('content-skeleton')))), 'id');

        foreach ($assignments as $elementId => $propertyMap) {
            static::assertContains($elementId, $knownIds);

            foreach ($propertyMap as $refId) {
                static::assertArrayHasKey($refId, $data);
            }
        }
    }

    #[TestDox('reaches the hydrated media entity through the image assignment of the decomposed format')]
    public function testDecomposedCarriesHydratedMediaEntity(): void
    {
        $this->createNestedLayout();

        $decomposed = $this->requestJson($this->uri('content-decomposed'));
        $assignments = $this->assignments($decomposed);

        static::assertArrayHasKey($this->ids->get('image'), $assignments);
        static::assertArrayHasKey('media', $assignments[$this->ids->get('image')]);

        $media = $this->dataMap($decomposed)[$assignments[$this->ids->get('image')]['media']];

        static::assertIsArray($media);
        static::assertSame($this->ids->get('media'), $media['id'] ?? null);
        // Fields only a hydrated media entity carries: an `['id' => …]` stub reaching the data map instead of
        // the loaded entity would satisfy the id assertion alone.
        static::assertSame(self::MEDIA_FILE_NAME, $media['fileName'] ?? null);
        static::assertSame(self::MEDIA_PATH, $media['path'] ?? null);
    }

    #[TestDox('resolves every data-format assignment to an entry in the data map')]
    public function testDataFormatAssignmentsAreReferentiallyIntact(): void
    {
        $this->createNestedLayout();

        $body = $this->requestJson($this->uri('content-data'));
        $assignments = $this->assignments($body);
        $data = $this->dataMap($body);

        // Both maps being empty would satisfy the difference below, so each is proven non-empty first.
        static::assertNotSame([], $assignments);
        static::assertNotSame([], $data);

        static::assertSame(
            [],
            array_values(array_diff($this->referencedRefIds($assignments), array_keys($data))),
            'Every ref an assignment names must be a key of the data map.',
        );
    }

    #[TestDox('serves decomposed skeleton nodes with id, component and the element alias at every depth, and no property values')]
    public function testDecomposedSkeletonNodesCarryStructureAndAliasButNoProperties(): void
    {
        $this->createNestedLayout();

        $body = $this->requestJson($this->uri('content-decomposed'));

        // The values did not vanish, they moved: `assignments` is where the properties of these very nodes went,
        // so their absence from the nodes below is the decomposition and not an empty render.
        static::assertNotSame([], $this->assignments($body));

        $nodes = $this->flattenWithDepth($this->decomposedSkeletons($body));

        // The fixture is a root, two children and a grandchild, so the per-node assertions below really are
        // exercised past depth 1 rather than on a single-level forest.
        static::assertSame([1, 2, 2, 3], array_column($nodes, 'depth'));

        $shapes = [];
        foreach ($nodes as $node) {
            $shapes[] = [
                'id' => $node['element']['id'] ?? null,
                'component' => $node['element']['component'] ?? null,
                'hasProperties' => \array_key_exists('properties', $node['element']),
                'apiAlias' => $node['element']['apiAlias'] ?? null,
            ];
        }

        static::assertSame([
            ['id' => $this->ids->get('root-grid'), 'component' => 'Sw:Grid:Container', 'hasProperties' => false, 'apiAlias' => 'content_skeleton_element'],
            ['id' => $this->ids->get('text'), 'component' => 'Sw:Content:Text', 'hasProperties' => false, 'apiAlias' => 'content_skeleton_element'],
            ['id' => $this->ids->get('inner-grid'), 'component' => 'Sw:Grid:Container', 'hasProperties' => false, 'apiAlias' => 'content_skeleton_element'],
            ['id' => $this->ids->get('image'), 'component' => 'Sw:Media:Image', 'hasProperties' => false, 'apiAlias' => 'content_skeleton_element'],
        ], $shapes);
    }

    #[TestDox('names only elements the skeleton response carries in the assignments of a data response')]
    public function testDataAssignmentsNameOnlyElementsTheSkeletonResponseCarries(): void
    {
        $this->createNestedLayout();

        $skeletonIds = array_column($this->flatten($this->rootElements($this->requestJson($this->uri('content-skeleton')))), 'id');
        $assignments = $this->assignments($this->requestJson($this->uri('content-data')));

        // Four distinct ids over three depths on the skeleton side and a non-empty assignment map on the data
        // side, so the composition below is a correspondence between two populated maps.
        static::assertCount(4, $skeletonIds);
        static::assertNotSame([], $assignments);

        static::assertSame(
            [],
            array_values(array_diff(array_keys($assignments), $skeletonIds)),
            'A data response may only assign to element ids the cached skeleton response already holds.',
        );
    }

    #[TestDox('assigns to every skeleton element the value index holds an entry for')]
    public function testEverySkeletonElementWithAnIndexEntryIsAssignedTo(): void
    {
        $this->createNestedLayout();

        $skeletonIds = array_column($this->flatten($this->rootElements($this->requestJson($this->uri('content-skeleton')))), 'id');
        $indexedIds = $this->propertyBearingElementIds();
        $assignments = $this->assignments($this->requestJson($this->uri('content-data')));

        // The qualifier is load-bearing: `ResolvedValueIndexFactory` writes no assignment entry for an element
        // with zero rendered properties, so the reverse direction is stated over the elements the index holds
        // an entry for rather than over every skeleton id.
        static::assertNotSame([], $indexedIds);
        static::assertSame([], array_values(array_diff($indexedIds, $skeletonIds)));

        static::assertSame(
            [],
            array_values(array_diff($indexedIds, array_keys($assignments))),
            'Every skeleton element the value index holds an entry for must carry its assignments entry.',
        );
    }

    #[TestDox('serves only the addressed subtree when the prune had to keep the target\'s context-providing ancestor')]
    public function testPartialRenderExtractsATargetWhoseAncestorThePruneKeeps(): void
    {
        $this->createContextDependentNestedLayout();

        // Fixture guard: the target is itself a context consumer, so `findDataRootIndex()` cannot stop at the
        // target's own index and the prune has to keep the ancestor above it.
        $ancestor = $this->storedRoots()[0];
        static::assertSame($this->ids->get('root-grid'), $ancestor->id);
        $target = $ancestor->slots['content'][0];
        static::assertSame($this->ids->get('inner-grid'), $target->id);
        static::assertTrue((new ContextDependencyAnalyzer())->requiresParentData($target));

        // The ancestor really is part of the whole-layout render, so its absence from the partial body below
        // is something the render removed rather than something the fixture never had.
        static::assertContains($this->ids->get('root-grid'), $this->servedElementIds());

        $partialRoots = $this->rootElements(
            $this->requestJson($this->uri('content') . '?elementId=' . $this->ids->get('inner-grid'))
        );

        static::assertSame([$this->ids->get('inner-grid')], array_column($partialRoots, 'id'));

        // The complete flattened id list: the ancestor is gone and the target's own descendant is not.
        $servedIds = array_column($this->flatten($partialRoots), 'id');
        static::assertSame([$this->ids->get('inner-grid'), $this->ids->get('text')], $servedIds);
        static::assertNotContains($this->ids->get('root-grid'), $servedIds);

        // The ancestor was still in the tree when the render step ran: context is distributed parent to child
        // only, so a target that carries the redistributed page-level value can only have received it from an
        // ancestor the prune kept. The value's absence would mean the prune had already dropped the ancestor,
        // leaving nothing for the extract to remove.
        static::assertIsArray($partialRoots[0]['properties'] ?? null);
        static::assertSame(
            $this->ids->get('category'),
            $partialRoots[0]['properties'][self::PAGE_CONTEXT_PROPERTY] ?? null,
        );
    }

    #[TestDox('fails a partial render on a wiring defect in a sibling subtree the prune discards')]
    public function testRedistributeExpansionValidatesSubtreesThePartialRenderDiscards(): void
    {
        $this->createDottedRedistributeSiblingLayout();
        $this->assertStoredDottedRedistributeConsumer();
        $this->assertStoredTextIsOutsideTheInnerGridSubtree();

        $this->browser->request('GET', $this->uri('content') . '?elementId=' . $this->ids->get('inner-grid'));

        $this->assertErrorCode(Response::HTTP_BAD_REQUEST, ContentSystemException::REDISTRIBUTE_DOTTED_PATH);
    }

    #[TestDox('keeps the skeleton structurally identical to the full format while a finalization listener rewrites both trees')]
    public function testSkeletonIsFullMinusPropertiesUnderAFinalizationListener(): void
    {
        $this->createNestedLayout();

        // Fixture guard: the stored order is text before inner-grid, so a served order of inner-grid before
        // text can only be the listener's reversal and not the layout as authored.
        static::assertSame(
            [$this->ids->get('text'), $this->ids->get('inner-grid')],
            array_map(static fn (StoredElement $element): string => $element->id, $this->storedRoots()[0]->slots['content']),
        );

        /** @var list<list<string>> $invocations */
        $invocations = [];
        $this->registerFinalizationListener(function (RenderedTreeFinalizationEvent $event) use (&$invocations): void {
            $invocations[] = $this->contentElementIds($event->tree());

            $event->replaceTree(array_map($this->reverseSlotOrder(...), $event->tree()));
        });

        $full = $this->rootElements($this->requestJson($this->uri('content')));

        // The listener is observed to have run inside the full request before anything is concluded from that
        // response — a listener that silently never fires would make every assertion below vacuous.
        static::assertCount(1, $invocations, 'The finalization listener must run inside the full-format request.');

        $skeleton = $this->rootElements($this->requestJson($this->uri('content-skeleton')));

        static::assertCount(2, $invocations, 'The finalization listener must run inside the skeleton request too.');
        static::assertSame($invocations[0], $invocations[1], 'Both modes must hand the listener the same forest.');

        // The listener really transformed the served tree rather than handing back what it was given.
        static::assertSame(
            [$this->ids->get('inner-grid'), $this->ids->get('text')],
            array_column($this->slotChildren($full[0], 'content'), 'id'),
        );

        // The two requests really were the two modes: only the full one carries property values at all.
        static::assertArrayHasKey('properties', $full[0]);
        static::assertNotSame([], $full[0]['properties']);
        static::assertArrayNotHasKey('properties', $skeleton[0]);

        static::assertSame(
            array_map($this->structuralProjection(...), $full),
            array_map($this->structuralProjection(...), $skeleton),
        );
    }

    #[TestDox('serves the resolved-value index\'s primitive keys and their order from the cached element-type specification')]
    public function testIndexPrimitiveKeysAndOrderComeFromTheCachedElementTypeSpecification(): void
    {
        $this->createNestedLayout();

        $pool = $this->elementTypeCachePool();

        $pool->deleteItem(self::ELEMENT_TYPE_CACHE_KEY);
        static::assertFalse(
            $pool->getItem(self::ELEMENT_TYPE_CACHE_KEY)->isHit(),
            'The element-type cache must be cold before the render.',
        );

        $rendered = $this->requestJson($this->uri('content-data'));

        // The entry was evicted and is back, so the request that just ran populated it: the registry really
        // does warm this backing entry from inside the request rather than reading around it.
        $cachedTypes = $pool->getItem(self::ELEMENT_TYPE_CACHE_KEY);
        static::assertTrue($cachedTypes->isHit(), 'A render must leave the element-type cache populated.');

        // The image element's assignment keys arrive in exactly the order the CACHED spec declares its
        // primitives in, followed by the loader-resolved key. This does not isolate which reader of the cache
        // enforces that order, for the same reason the probe below states.
        $declaredPrimitives = $this->declaredPrimitiveKeys($cachedTypes->get(), 'Sw:Media:Image');
        static::assertNotSame([], $declaredPrimitives);
        static::assertSame(
            [...$declaredPrimitives, 'media'],
            array_keys($this->assignments($rendered)[$this->ids->get('image')] ?? []),
            'The cached element-type specification must order the image element\'s emitted keys.',
        );

        // The dependency proof, observed rather than inferred. A render served while the cached map is missing
        // the image type must lose that element's declared primitives from the index — only a render that READS
        // this cache entry can react to what was put in it, so a run that recomputed the types instead would
        // serve the unchanged five keys here and fail. This does not isolate the index build's own registry
        // read: `RenderedElementFactory` reads the same cache entry to decide which declared primitives exist
        // at all, so the probe proves the served index depends on the cached specification without attributing
        // that dependency to one reader over the other.
        $original = $cachedTypes->get();
        $poisoned = $original;
        static::assertIsArray($poisoned);
        unset($poisoned['Sw:Media:Image']);
        static::assertTrue(
            $this->overwriteElementTypeCache($pool, $poisoned),
            'The element-type cache entry must be writable from the test.',
        );

        try {
            $probe = $this->requestJson($this->uri('content-data'));

            static::assertSame(
                ['media'],
                array_keys($this->assignments($probe)[$this->ids->get('image')] ?? []),
                'A render reading the cached element types must emit no declared primitive for a type the cache no '
                . 'longer carries.',
            );
        } finally {
            // Restore what the earlier render computed, so no later test in this process observes the poisoned map.
            static::assertTrue(
                $this->overwriteElementTypeCache($pool, $original),
                'The element-type cache entry must be writable from the test.',
            );
        }
    }

    #[TestDox('keeps the page-level virtual root out of the response')]
    public function testVirtualRootDoesNotLeak(): void
    {
        $this->createNestedLayout();

        // The category root source really does declare page data requirements, which is what makes the
        // pipeline wrap the roots in the first place.
        static::assertNotSame([], $this->pageDataRequirementKeys());

        $roots = $this->rootElements($this->requestJson($this->uri('content')));

        static::assertSame([$this->ids->get('root-grid')], array_column($roots, 'id'));

        foreach ($this->flatten($roots) as $element) {
            static::assertNotSame(VirtualRootWrapper::VIRTUAL_ROOT_ID, $element['id']);
        }
    }

    /**
     * All four formats, because the refusal sits in `ContentRoute::load()` ahead of the format's own factory
     * and skeleton is the format a response-side filter would have missed.
     */
    #[DataProvider('contentFormatProvider')]
    #[TestDox('rejects an includes parameter on the $format route with a 400 before it renders anything')]
    public function testEveryFormatRejectsIncludes(string $format): void
    {
        $this->createNestedLayout();

        // The same route serves a 200 without the parameter, so the 400 below is the parameter and not a
        // broken fixture.
        $this->requestJson($this->uri($format));

        $this->browser->request('GET', $this->uri($format) . '?includes[content_page][]=id');

        $this->assertErrorCode(Response::HTTP_BAD_REQUEST, ContentSystemException::FIELD_SELECTION_NOT_SUPPORTED);
    }

    #[DataProvider('contentFormatProvider')]
    #[TestDox('rejects an excludes parameter on the $format route with a 400 before it renders anything')]
    public function testEveryFormatRejectsExcludes(string $format): void
    {
        $this->createNestedLayout();

        $this->browser->request('GET', $this->uri($format) . '?excludes[content_element][]=properties');

        $this->assertErrorCode(Response::HTTP_BAD_REQUEST, ContentSystemException::FIELD_SELECTION_NOT_SUPPORTED);
    }

    #[TestDox('names the offending parameter in the field-selection rejection')]
    public function testFieldSelectionRejectionNamesTheOffendingParameter(): void
    {
        $this->createNestedLayout();

        $this->browser->request('GET', $this->uri('content') . '?excludes[content_element][]=properties');

        $detail = $this->errorDetail();

        static::assertStringContainsString('excludes', $detail);
        static::assertStringNotContainsString('includes', $detail, 'The message must name the parameter that was actually sent.');
    }

    #[TestDox('rejects a dotted field selector rather than serving an unfiltered body')]
    public function testDottedFieldSelectorIsRejected(): void
    {
        $this->createNestedLayout();

        $this->browser->request('GET', $this->uri('content') . '?includes[content_page][]=elements.component');

        $this->assertErrorCode(Response::HTTP_BAD_REQUEST, ContentSystemException::FIELD_SELECTION_NOT_SUPPORTED);
    }

    #[TestDox('fails a wiring-defective layout in the full rendering mode')]
    public function testWiringDefectFailsInFullMode(): void
    {
        $this->createDottedRedistributeLayout();
        $this->assertStoredDottedRedistributeConsumer();

        $this->browser->request('GET', $this->uri('content'));

        $this->assertErrorCode(Response::HTTP_BAD_REQUEST, ContentSystemException::REDISTRIBUTE_DOTTED_PATH);
    }

    #[TestDox('fails the same wiring-defective layout in the skeleton rendering mode')]
    public function testWiringDefectFailsInSkeletonMode(): void
    {
        $this->createDottedRedistributeLayout();
        $this->assertStoredDottedRedistributeConsumer();

        $this->browser->request('GET', $this->uri('content-skeleton'));

        $this->assertErrorCode(Response::HTTP_BAD_REQUEST, ContentSystemException::REDISTRIBUTE_DOTTED_PATH);
    }

    #[TestDox('fails with element_not_found instead of an empty success when elementId names no element')]
    public function testUnknownElementIdFails(): void
    {
        $this->createNestedLayout();

        $unknownId = $this->ids->get('not-in-this-layout');
        static::assertNotContains($unknownId, $this->servedElementIds());

        $this->browser->request('GET', $this->uri('content') . '?elementId=' . $unknownId);

        $this->assertErrorCode(Response::HTTP_NOT_FOUND, ContentSystemException::ELEMENT_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contentFormatProvider(): iterable
    {
        yield 'full' => ['content'];
        yield 'decomposed' => ['content-decomposed'];
        yield 'skeleton' => ['content-skeleton'];
        yield 'data' => ['content-data'];
    }

    /**
     * The stored data requirements of the fixture's image element, so a test asserting their absence from a
     * response knows they were authored in the first place.
     *
     * @return array<string, mixed>
     */
    private function storedImageDataRequirements(): array
    {
        $image = $this->storedRoots()[0]->slots['content'][1]->slots['content'][0];

        static::assertSame($this->ids->get('image'), $image->id);

        return $image->dataRequirements;
    }

    /**
     * The fixture image's `url` as an independent `media.repository` search hands it back, read through a
     * fresh search rather than through anything the content route's rendering pipeline itself calls. `url` is
     * a Runtime, ApiAware field computed at read time from `path`/`private`/`updatedAt` (falling back to
     * `createdAt`), so its value carries a per-run timestamp; reading it off a second, independent load of the
     * same persisted entity keeps that timestamp matching by construction instead of stripping it.
     */
    private function independentlyLoadedMediaUrl(): string
    {
        $media = $this->repository('media.repository')
            ->search(new Criteria([$this->ids->get('media')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(MediaEntity::class, $media);

        return $media->getUrl();
    }

    /**
     * @return array<string, mixed>
     */
    private function fullFormatMediaPayload(): array
    {
        foreach ($this->flatten($this->rootElements($this->requestJson($this->uri('content')))) as $element) {
            if (($element['id'] ?? null) !== $this->ids->get('image')) {
                continue;
            }

            static::assertIsArray($element['properties'] ?? null);
            static::assertIsArray($element['properties']['media'] ?? null);

            return $element['properties']['media'];
        }

        static::fail('The full-format response carries no image element.');
    }

    /**
     * @param list<RenderedElement> $elements
     *
     * @return list<string>
     */
    private function contentElementIds(array $elements): array
    {
        $ids = [];
        foreach ($elements as $element) {
            $ids[] = $element->id;
            foreach ($element->slots as $children) {
                foreach ($this->contentElementIds($children) as $descendant) {
                    $ids[] = $descendant;
                }
            }
        }

        return $ids;
    }

    private function registerFinalizationListener(\Closure $listener): void
    {
        $this->finalizationListener = $listener;
        $this->eventDispatcher()->addListener(RenderedTreeFinalizationEvent::class, $listener);
    }

    /**
     * `cache.system` is the pool the cached element-type registry reads its specification map through, so
     * evicting and rewriting this pool's entry is what puts a render into a cold or a warm backing-cache
     * state from outside the registry.
     */
    private function elementTypeCachePool(): AdapterInterface
    {
        $pool = static::getContainer()->get('cache.system');
        static::assertInstanceOf(AdapterInterface::class, $pool);

        return $pool;
    }

    private function overwriteElementTypeCache(AdapterInterface $pool, mixed $types): bool
    {
        $item = $pool->getItem(self::ELEMENT_TYPE_CACHE_KEY);
        $item->set($types);

        return $pool->save($item);
    }

    /**
     * The primitive property keys `$type` declares, read off the CACHED specification map rather than off a
     * fresh registry load, in declaration order — the order
     * {@see ResolvedValueIndexFactory} emits an element's
     * declared primitives in.
     *
     * @return list<string>
     */
    private function declaredPrimitiveKeys(mixed $cached, string $type): array
    {
        static::assertIsArray($cached);
        static::assertArrayHasKey($type, $cached);

        $specification = $cached[$type];
        static::assertInstanceOf(ContentSystemElementTypeSpecification::class, $specification);

        $keys = [];
        foreach ($specification->properties() as $key => $property) {
            if ($property->type()->isPrimitive()) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function eventDispatcher(): EventDispatcherInterface
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher;
    }

    /**
     * Reverses every slot's child list, forest-wide. The decision reads slot membership and child order only,
     * both of which a skeleton render mints exactly as a full one does, so the transformation is the same in
     * both modes. Nothing here looks at a property value, which is the one part of a rendered element that
     * does differ by mode.
     */
    private function reverseSlotOrder(RenderedElement $element): RenderedElement
    {
        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = array_reverse(array_map($this->reverseSlotOrder(...), $children));
        }

        return $element->withSlots($slots);
    }

    private function uri(string $format): string
    {
        return '/store-api/' . $format . '/category/' . $this->ids->get('category');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $uri): array
    {
        $this->browser->request('GET', $uri);
        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);

        return $body;
    }

    /**
     * The `detail` of the single error the last response carries, so a test can assert what the message names.
     */
    private function errorDetail(): string
    {
        $body = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($body);
        static::assertIsArray($body['errors'] ?? null);
        static::assertCount(1, $body['errors']);
        static::assertIsArray($body['errors'][0]);
        static::assertIsString($body['errors'][0]['detail'] ?? null);

        return $body['errors'][0]['detail'];
    }

    private function assertErrorCode(int $status, string $code): void
    {
        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame($status, $response->getStatusCode(), $content);

        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertArrayHasKey('errors', $body);
        static::assertIsArray($body['errors']);
        static::assertSame([$code], array_column($body['errors'], 'code'));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<array<string, mixed>>
     */
    private function rootElements(array $body): array
    {
        static::assertArrayHasKey('elements', $body);
        static::assertIsArray($body['elements']);

        $elements = [];
        foreach ($body['elements'] as $element) {
            static::assertIsArray($element);
            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, array<string, string>>
     */
    private function assignments(array $body): array
    {
        static::assertArrayHasKey('assignments', $body);
        static::assertIsArray($body['assignments']);

        $assignments = [];
        foreach ($body['assignments'] as $elementId => $propertyMap) {
            static::assertIsArray($propertyMap);

            $entries = [];
            foreach ($propertyMap as $propertyKey => $refId) {
                static::assertIsString($refId);
                $entries[(string) $propertyKey] = $refId;
            }

            $assignments[(string) $elementId] = $entries;
        }

        return $assignments;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function dataMap(array $body): array
    {
        static::assertArrayHasKey('data', $body);
        static::assertIsArray($body['data']);

        $data = [];
        foreach ($body['data'] as $refId => $value) {
            $data[(string) $refId] = $value;
        }

        return $data;
    }

    /**
     * All three structural formats serialize a slot as a plain child list, each through its own encoder. Every
     * structural comparison reads slots through here, so a format that started keying something other than a
     * child under a slot name shows up as a failure here rather than as a diff in each comparison.
     *
     * @param array<string, mixed> $element
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function slots(array $element): array
    {
        if (!\array_key_exists('slots', $element)) {
            return [];
        }

        static::assertIsArray($element['slots']);

        $slots = [];
        foreach ($element['slots'] as $slotName => $children) {
            static::assertIsArray($children);

            $list = [];
            foreach ($children as $key => $child) {
                if ($key === 'apiAlias') {
                    continue;
                }

                static::assertIsArray($child);
                $list[] = $child;
            }

            $slots[(string) $slotName] = $list;
        }

        return $slots;
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return list<array<string, mixed>>
     */
    private function slotChildren(array $element, string $slot): array
    {
        $slots = $this->slots($element);
        static::assertArrayHasKey($slot, $slots);

        return $slots[$slot];
    }

    /**
     * Reduces an element of any format to the parts a skeleton is supposed to keep: id, component, style and
     * the slot structure.
     *
     * @param array<string, mixed> $element
     *
     * @return array<string, mixed>
     */
    private function structuralProjection(array $element): array
    {
        $slots = [];
        foreach ($this->slots($element) as $slotName => $children) {
            $slots[$slotName] = array_map($this->structuralProjection(...), $children);
        }
        ksort($slots);

        return [
            'id' => $element['id'] ?? null,
            'component' => $element['component'] ?? null,
            'style' => $element['style'] ?? null,
            'slots' => $slots,
        ];
    }

    /**
     * The decomposed format's root skeleton list, read the way every other body part is read here.
     *
     * @param array<string, mixed> $body
     *
     * @return list<array<string, mixed>>
     */
    private function decomposedSkeletons(array $body): array
    {
        static::assertArrayHasKey('skeletons', $body);
        static::assertIsArray($body['skeletons']);

        $skeletons = [];
        foreach ($body['skeletons'] as $skeleton) {
            static::assertIsArray($skeleton);
            $skeletons[] = $skeleton;
        }

        return $skeletons;
    }

    /**
     * Every ref any assignment names, once each, so a test can compare the referenced set against the data map.
     *
     * @param array<string, array<string, string>> $assignments
     *
     * @return list<string>
     */
    private function referencedRefIds(array $assignments): array
    {
        $refs = [];
        foreach ($assignments as $propertyMap) {
            foreach ($propertyMap as $refId) {
                $refs[$refId] = true;
            }
        }

        return array_keys($refs);
    }

    /**
     * The element ids the resolved value index holds an entry for, read off the FULL format rather than off the
     * index: `ContentPageEncoder` writes the same rendered property map `ResolvedValueIndexFactory` walks, so a
     * non-empty `properties` there is exactly an assignments entry in the two index-reading formats. Reading it
     * off `assignments` instead would make any composition assertion over it circular.
     *
     * @return list<string>
     */
    private function propertyBearingElementIds(): array
    {
        $ids = [];
        foreach ($this->flatten($this->rootElements($this->requestJson($this->uri('content')))) as $element) {
            static::assertIsArray($element['properties'] ?? null);

            if ($element['properties'] === []) {
                continue;
            }

            static::assertIsString($element['id'] ?? null);
            $ids[] = $element['id'];
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $elements
     *
     * @return list<array{element: array<string, mixed>, depth: int}>
     */
    private function flattenWithDepth(array $elements, int $depth = 1): array
    {
        $flat = [];
        foreach ($elements as $element) {
            $flat[] = ['element' => $element, 'depth' => $depth];
            foreach ($this->slots($element) as $children) {
                foreach ($this->flattenWithDepth($children, $depth + 1) as $descendant) {
                    $flat[] = $descendant;
                }
            }
        }

        return $flat;
    }

    /**
     * @param list<array<string, mixed>> $elements
     *
     * @return list<array<string, mixed>>
     */
    private function flatten(array $elements): array
    {
        $flat = [];
        foreach ($elements as $element) {
            $flat[] = $element;
            foreach ($this->slots($element) as $children) {
                foreach ($this->flatten($children) as $descendant) {
                    $flat[] = $descendant;
                }
            }
        }

        return $flat;
    }

    /**
     * @return list<string>
     */
    private function servedElementIds(): array
    {
        return array_column($this->flatten($this->rootElements($this->requestJson($this->uri('content')))), 'id');
    }

    private function createNestedLayout(): void
    {
        $this->createCategory();
        $this->createMedia();
        $this->persistLayout([[
            'id' => $this->ids->get('root-grid'),
            'component' => 'Sw:Grid:Container',
            'properties' => [],
            'style' => ['col-span' => ['xs' => 6]],
            // Consuming the page-level context the category root source declares is what makes the
            // virtual-root wrap/unwrap lifecycle observable in the response at all.
            'acceptsContext' => [
                self::PAGE_CONTEXT_KEY => [
                    'type' => 'single',
                    'required' => false,
                    'propertyAlias' => self::PAGE_CONTEXT_PROPERTY,
                ],
            ],
            'slots' => [
                'content' => [
                    [
                        'id' => $this->ids->get('text'),
                        'component' => 'Sw:Content:Text',
                        'properties' => ['text' => self::TEXT_VALUE],
                    ],
                    [
                        'id' => $this->ids->get('inner-grid'),
                        'component' => 'Sw:Grid:Container',
                        'properties' => [],
                        'style' => ['col-span' => ['md' => 4]],
                        'slots' => [
                            'content' => [[
                                'id' => $this->ids->get('image'),
                                'component' => 'Sw:Media:Image',
                                'properties' => ['mediaId' => $this->ids->get('media')],
                                'dataRequirements' => [
                                    'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ]]);
    }

    /**
     * A partial-render target that is itself a context consumer: the root grid redistributes the page-level
     * `category` context it receives, and the nested target consumes `category.id` out of that redistribution.
     * The target therefore answers `requiresParentData()` true, so the pre-render prune keeps the ancestor for
     * the sake of that delivery and the post-render extract is the only step that removes it again.
     *
     * Deliberately a second builder rather than a variation of `createNestedLayout()`: that fixture backs most
     * of this file, and there the only context consumer is the root itself.
     */
    private function createContextDependentNestedLayout(): void
    {
        $this->createCategory();
        $this->persistLayout([[
            'id' => $this->ids->get('root-grid'),
            'component' => 'Sw:Grid:Container',
            'properties' => [],
            // Undotted, because a redistributing consumer may not be keyed by a path, and unaliased, so the
            // derived broadcast provider hands the category on to children under the key they declare.
            'acceptsContext' => [
                'category' => [
                    'type' => 'single',
                    'required' => false,
                    'redistribute' => true,
                ],
            ],
            'slots' => [
                'content' => [[
                    'id' => $this->ids->get('inner-grid'),
                    'component' => 'Sw:Grid:Container',
                    'properties' => [],
                    'acceptsContext' => [
                        self::PAGE_CONTEXT_KEY => [
                            'type' => 'single',
                            'required' => false,
                            'propertyAlias' => self::PAGE_CONTEXT_PROPERTY,
                        ],
                    ],
                    'slots' => [
                        'content' => [[
                            'id' => $this->ids->get('text'),
                            'component' => 'Sw:Content:Text',
                            'properties' => ['text' => self::TEXT_VALUE],
                        ]],
                    ],
                ]],
            ],
        ]]);
    }

    /**
     * A category carrying a canonical seo url, consumed whole by the layout root: an undotted, unaliased
     * consumer takes the delivered value as it stands, so the hydrated `CategoryEntity` — a seo-aware one —
     * becomes the value of the root's `category` property rather than a scalar off it.
     *
     * Deliberately a third builder rather than a variation of `createNestedLayout()` or
     * `createContextDependentNestedLayout()`: neither of those consumes the entity undotted (both take
     * `category.id`, a string), and neither category carries a seo url for anything to enrich.
     */
    private function createSeoAwareCategoryLayout(): void
    {
        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Content route seo category',
            'active' => true,
            'seoUrls' => [[
                'id' => $this->ids->create('seo-url'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
                'pathInfo' => 'content-route-seo',
                'seoPathInfo' => 'content-route-seo',
                'isCanonical' => true,
            ]],
        ]], Context::createDefaultContext());

        $this->persistLayout([[
            'id' => $this->ids->get('root-grid'),
            'component' => 'Sw:Grid:Container',
            'properties' => [],
            'acceptsContext' => [
                self::SEO_CONTEXT_PROPERTY => [
                    'type' => 'single',
                    'required' => false,
                ],
            ],
        ]]);
    }

    /**
     * The canonical seo-url ids `StoreApiSeoResolver::enrich()` would find for the fixture category, read on
     * three of its four filters: canonical, the route name registered for the category definition, and that
     * category as the foreign key. The resolver's fourth filter, languageId, is satisfied without being
     * reproduced here: the fixture's seo-url row and `createSalesChannelBrowser()` both use the system language.
     *
     * @return list<string>
     */
    private function storedCanonicalSeoUrlIds(): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isCanonical', true));
        $criteria->addFilter(new EqualsFilter('routeName', TestNavigationSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('foreignKey', $this->ids->get('category')));

        $ids = [];
        foreach ($this->repository('seo_url.repository')->searchIds($criteria, Context::createDefaultContext())->getIds() as $id) {
            static::assertIsString($id);
            $ids[] = $id;
        }

        return $ids;
    }

    private function createDottedRedistributeLayout(): void
    {
        $this->createCategory();
        $this->persistLayout([[
            'id' => $this->ids->get('root-grid'),
            'component' => 'Sw:Grid:Container',
            'properties' => [],
            'slots' => [
                'content' => [$this->dottedRedistributeText()],
            ],
        ]]);
        $this->replaceRedistributeSentinelWithDottedKey();
    }

    /**
     * The same wiring defect as `createDottedRedistributeLayout()`, but parked on a sibling of an otherwise
     * renderable `inner-grid` subtree, so a partial render addressed at that subtree prunes the defect away.
     */
    private function createDottedRedistributeSiblingLayout(): void
    {
        $this->createCategory();
        $this->createMedia();
        $this->persistLayout([[
            'id' => $this->ids->get('root-grid'),
            'component' => 'Sw:Grid:Container',
            'properties' => [],
            'slots' => [
                'content' => [
                    $this->dottedRedistributeText(),
                    [
                        'id' => $this->ids->get('inner-grid'),
                        'component' => 'Sw:Grid:Container',
                        'properties' => [],
                        'slots' => [
                            'content' => [[
                                'id' => $this->ids->get('image'),
                                'component' => 'Sw:Media:Image',
                                'properties' => ['mediaId' => $this->ids->get('media')],
                                'dataRequirements' => [
                                    'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ]]);
        $this->replaceRedistributeSentinelWithDottedKey();
    }

    /**
     * The write path rejects a redistributing consumer keyed by a dotted path ({@see ContentSystemException::REDISTRIBUTE_DOTTED_PATH}),
     * so the defect this file exercises cannot reach storage through the DAL at all. This element is authored
     * under a single-segment sentinel key instead, which passes write validation, and
     * {@see replaceRedistributeSentinelWithDottedKey()} rewrites it to the real dotted key after persistence —
     * the render-time defense remains the sole authority for a tree that bypassed the DAL this way, exactly as
     * it is for a migration or a raw SQL write.
     *
     * @return array<string, mixed>
     */
    private function dottedRedistributeText(): array
    {
        return [
            'id' => $this->ids->get('text'),
            'component' => 'Sw:Content:Text',
            'properties' => ['text' => self::TEXT_VALUE],
            'acceptsContext' => [
                'categoryPlaceholder' => [
                    'type' => 'single',
                    'required' => false,
                    'redistribute' => true,
                ],
            ],
        ];
    }

    /**
     * Rewrites the sentinel key {@see dottedRedistributeText()} authors under (`categoryPlaceholder`) to the
     * real dotted key (`category.name`) directly in the persisted `content_layout` row, bypassing the DAL write
     * path the way a migration or a raw SQL write would. The persisted row is unreadable through
     * {@see StoredElementCodec} by design from this point on; {@see assertStoredDottedRedistributeConsumer()}
     * and {@see assertStoredTextIsOutsideTheInnerGridSubtree()} verify it through raw SQL instead.
     */
    private function replaceRedistributeSentinelWithDottedKey(): void
    {
        $this->connection()->executeStatement(
            'UPDATE `content_layout` SET `layout` = REPLACE(`layout`, :sentinel, :dotted) WHERE `id` = :id',
            [
                'sentinel' => '"categoryPlaceholder"',
                'dotted' => '"category.name"',
                'id' => Uuid::fromHexToBytes($this->ids->get('layout')),
            ]
        );
    }

    /**
     * @param list<array<string, mixed>> $tree
     */
    private function persistLayout(array $tree): void
    {
        $context = Context::createDefaultContext();

        $this->layoutRepository()->create([[
            'id' => $this->ids->get('layout'),
            'name' => self::LAYOUT_NAME,
            'version' => self::LAYOUT_VERSION,
            'rootSource' => 'category',
            'layout' => $tree,
        ]], $context);

        $this->repository('category_content_layout.repository')->create([[
            'id' => $this->ids->get('assignment'),
            'categoryId' => $this->ids->get('category'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    private function createCategory(): void
    {
        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Content route category',
            'active' => true,
        ]], Context::createDefaultContext());
    }

    private function createMedia(): void
    {
        $this->repository('media.repository')->create([[
            'id' => $this->ids->create('media'),
            'fileName' => self::MEDIA_FILE_NAME,
            'fileExtension' => 'png',
            'mimeType' => 'image/png',
            'path' => self::MEDIA_PATH,
            'private' => false,
        ]], Context::createDefaultContext());
    }

    private function assertStoredStyleIsPresent(): void
    {
        $root = $this->storedRoots()[0];

        static::assertSame(['col-span' => ['xs' => 6]], $root->style->toArray());
        static::assertSame(['col-span' => ['md' => 4]], $root->slots['content'][1]->style->toArray());
    }

    /**
     * Verified through the raw persisted JSON rather than through {@see storedRoots()}: the row this asserts
     * over carries the dotted-path defect {@see StoredElementCodec} rejects by design, so a codec-based read
     * would throw before this assertion ever ran.
     */
    private function assertStoredDottedRedistributeConsumer(): void
    {
        $child = $this->rawStoredLayoutRoots()[0]['slots']['content'][0] ?? null;
        static::assertIsArray($child);

        $consumer = $child['acceptsContext']['category.name'] ?? null;

        static::assertIsArray($consumer, 'The persisted layout must really carry the dotted redistribute consumer.');
        static::assertTrue($consumer['redistribute'] ?? false);
    }

    private function assertStoredPageContextConsumer(): void
    {
        $root = $this->storedRoots()[0];
        $consumer = $root->contextDefinitions->getAllConsumers()[self::PAGE_CONTEXT_KEY] ?? null;

        static::assertNotNull($consumer, 'The persisted root must really consume the page-level context key.');
        static::assertSame(self::PAGE_CONTEXT_PROPERTY, $consumer->propertyAlias);
    }

    /**
     * The defect-carrying `text` element really is a sibling of the partial-render target rather than one of
     * its descendants, so the pre-hydration prune drops it before hydration would ever see it.
     *
     * Verified through the raw persisted JSON rather than through {@see storedRoots()}, for the same reason as
     * {@see assertStoredDottedRedistributeConsumer()}.
     */
    private function assertStoredTextIsOutsideTheInnerGridSubtree(): void
    {
        $children = $this->rawStoredLayoutRoots()[0]['slots']['content'] ?? null;
        static::assertIsArray($children);

        static::assertSame($this->ids->get('text'), $children[0]['id'] ?? null);
        static::assertSame($this->ids->get('inner-grid'), $children[1]['id'] ?? null);
        static::assertSame(
            [$this->ids->get('image')],
            array_column($children[1]['slots']['content'] ?? [], 'id'),
        );
    }

    /**
     * @return list<StoredElement>
     */
    private function storedRoots(): array
    {
        $layout = $this->layoutRepository()
            ->search(new Criteria([$this->ids->get('layout')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($layout);

        return $layout->getLayout();
    }

    /**
     * The fixture layout's persisted `layout` column, read and decoded directly rather than through the DAL:
     * the two callers above assert over a row a codec-based read cannot decode by design.
     *
     * @return list<array<string, mixed>>
     */
    private function rawStoredLayoutRoots(): array
    {
        $raw = $this->connection()->fetchOne(
            'SELECT `layout` FROM `content_layout` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('layout'))]
        );
        static::assertIsString($raw);

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return array_values($decoded);
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    /**
     * @return list<string>
     */
    private function pageDataRequirementKeys(): array
    {
        $definition = static::getContainer()->get(CategoryContentLayoutDefinition::class);
        static::assertInstanceOf(CategoryContentLayoutDefinition::class, $definition);

        return array_map(
            static fn ($requirement): string => $requirement->key,
            $definition->getPageDataRequirements(),
        );
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function layoutRepository(): EntityRepository
    {
        $repository = static::getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
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
