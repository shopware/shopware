<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\SalesChannel;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the encoded body each content route serves against the OpenAPI schema that same route's entry
 * declares for its 200 response, over all twelve generated routes: four output formats across the main,
 * header and footer section families.
 *
 * The schema is taken from the assembled Store API document rather than from a fragment file on disk: the
 * `$ref` comes off the route's own `responses.200.content.application/json.schema` entry, and the component it
 * names is resolved inside the same document. A page schema references its node schema by `$ref`, which only
 * resolves against the whole document, and taking the document is also what makes a component that stops being
 * reachable show up here. The request URL is derived from the same path key, so the body validated is the body
 * of the entry that declared the schema.
 *
 * The three families reach their layout by different resolution and therefore need different fixtures. Main
 * resolves by path, so its routes carry a `{path}` parameter and its layout is bound to a category. Header and
 * footer carry no path at all and resolve by sales channel through `DomainAwareLayoutResolver`, so their
 * layouts are bound by a `header_content_layout` / `footer_content_layout` assignment scoped to the browser's
 * own sales channel — the resolver's second tier, domain null and channel set. Nothing about that resolution is
 * stubbed: each row asserts the served page `id` is the fixture's own layout, so a route that resolved
 * something else fails rather than validating a body this test never built.
 *
 * Two fixtures per family, because the empty cases and the populated ones cannot be the same layout: a page
 * whose `data` and `assignments` maps are empty is a page on which no element resolved anything, which leaves
 * no populated tree to validate. `createPopulatedLayout()` carries nested slots, element style and hydrated as
 * well as primitive property values; `createEmptyCaseLayout()` carries a single element with no properties, no
 * slots and no style, which is simultaneously the page-level empty case.
 *
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class ContentRouteResponseSchemaConformanceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * The id the assembled Store API document's `components` node is registered under, so a `#/components/...`
     * pointer taken off a route entry resolves against it verbatim.
     */
    private const SCHEMA_DOCUMENT_URI = 'https://schema.shopware.test/store-api.json';

    private const SECTION_MAIN = 'main';

    private const SECTION_HEADER = 'header';

    private const SECTION_FOOTER = 'footer';

    /**
     * The root source each section's layout must be written under. The header and footer assignment gate is a
     * type-match against the section id, so a layout bound to one of those sections carries the section id
     * itself as its root source rather than an entity type.
     */
    private const ROOT_SOURCE_BY_SECTION = [
        self::SECTION_MAIN => 'category',
        self::SECTION_HEADER => 'header',
        self::SECTION_FOOTER => 'footer',
    ];

    private const ASSIGNMENT_REPOSITORY_BY_SECTION = [
        self::SECTION_MAIN => 'category_content_layout.repository',
        self::SECTION_HEADER => 'header_content_layout.repository',
        self::SECTION_FOOTER => 'footer_content_layout.repository',
    ];

    private const LAYOUT_NAME = 'content-route-schema-conformance';

    private const LAYOUT_VERSION = '1.0.0';

    private const TEXT_VALUE = 'Alpha copy';

    private const MEDIA_FILE_NAME = 'content-route-schema-probe';

    private const MEDIA_PATH = 'media/content-route-schema-probe.png';

    private IdsCollection $ids;

    private KernelBrowser $browser;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $storeApiDocument = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->browser = $this->createSalesChannelBrowser();
    }

    /**
     * One case per generated content route: the section that resolves it, and the path key its schema hangs
     * off in the assembled document.
     *
     * @return \Generator<string, array{string, string}>
     */
    public static function routeProvider(): \Generator
    {
        yield 'main full, ContentPage' => [self::SECTION_MAIN, '/content/{path}'];
        yield 'main decomposed, ContentDecomposedPage' => [self::SECTION_MAIN, '/content-decomposed/{path}'];
        yield 'main skeleton, ContentSkeletonPage' => [self::SECTION_MAIN, '/content-skeleton/{path}'];
        yield 'main data, ContentDataPage' => [self::SECTION_MAIN, '/content-data/{path}'];

        yield 'header full, ContentPage' => [self::SECTION_HEADER, '/content-header'];
        yield 'header decomposed, ContentDecomposedPage' => [self::SECTION_HEADER, '/content-header-decomposed'];
        yield 'header skeleton, ContentSkeletonPage' => [self::SECTION_HEADER, '/content-header-skeleton'];
        yield 'header data, ContentDataPage' => [self::SECTION_HEADER, '/content-header-data'];

        yield 'footer full, ContentPage' => [self::SECTION_FOOTER, '/content-footer'];
        yield 'footer decomposed, ContentDecomposedPage' => [self::SECTION_FOOTER, '/content-footer-decomposed'];
        yield 'footer skeleton, ContentSkeletonPage' => [self::SECTION_FOOTER, '/content-footer-skeleton'];
        yield 'footer data, ContentDataPage' => [self::SECTION_FOOTER, '/content-footer-data'];
    }

    #[DataProvider('routeProvider')]
    #[TestDox('validates a populated body against the schema its own route entry declares')]
    public function testPopulatedBodyValidatesAgainstTheSchemaItsRouteDeclares(string $section, string $schemaPath): void
    {
        $this->createPopulatedLayout($section);

        $body = $this->servedBody($schemaPath);

        static::assertSame(
            [],
            $this->schemaViolations($schemaPath, $body),
            \sprintf('The populated body of "%s" does not validate against %s.', $schemaPath, $this->declaredResponseSchemaRef($schemaPath)),
        );
    }

    #[DataProvider('routeProvider')]
    #[TestDox('validates an empty-case body against the schema its own route entry declares')]
    public function testEmptyCaseBodyValidatesAgainstTheSchemaItsRouteDeclares(string $section, string $schemaPath): void
    {
        $this->createEmptyCaseLayout($section);

        $body = $this->servedBody($schemaPath);

        static::assertSame(
            [],
            $this->schemaViolations($schemaPath, $body),
            \sprintf('The empty-case body of "%s" does not validate against %s.', $schemaPath, $this->declaredResponseSchemaRef($schemaPath)),
        );
    }

    /**
     * The element-level empty shape is a property of the shared encoders rather than of a section: one
     * `ContentRoute` class serves every section, and the encoding listener that replaces the body selects by
     * response class alone. Pinned once, over the main section.
     */
    #[TestDox('serves the empty-case element with an empty property map and no slots or style key at all')]
    public function testTheEmptyCaseElementReallyCarriesTheElementLevelEmptyCase(): void
    {
        $this->createEmptyCaseLayout(self::SECTION_MAIN);

        $elements = $this->decodeAsArray($this->requestBody($this->requestUri('/content/{path}')))['elements'] ?? null;
        static::assertIsArray($elements);
        static::assertCount(1, $elements);

        $element = $elements[0];
        static::assertIsArray($element);
        static::assertSame($this->ids->get('bare-text'), $element['id'] ?? null);
        static::assertSame([], $element['properties'] ?? null, 'The empty property map must reach the wire as [].');
        static::assertArrayNotHasKey('slots', $element);
        static::assertArrayNotHasKey('style', $element);
    }

    #[TestDox('serves the empty-case page with an empty data map and an empty assignments map')]
    public function testTheEmptyCasePageReallyCarriesThePageLevelEmptyCase(): void
    {
        $this->createEmptyCaseLayout(self::SECTION_MAIN);

        $body = $this->decodeAsArray($this->requestBody($this->requestUri('/content-data/{path}')));

        static::assertSame([], $body['data'] ?? null, 'The empty data map must reach the wire as [].');
        static::assertSame([], $body['assignments'] ?? null, 'The empty assignments map must reach the wire as [].');
    }

    /**
     * The instrument control. Without it a schema that resolved to something permissive — or a validator wired
     * to the wrong document — would report every body as conformant and this file would pass vacuously.
     */
    #[TestDox('names the JSON pointer and the schema keyword when a member the declared schema requires is missing')]
    public function testValidationNamesThePointerAndKeywordOfAMissingRequiredMember(): void
    {
        $this->createPopulatedLayout(self::SECTION_MAIN);

        $members = get_object_vars($this->servedBody('/content/{path}'));
        static::assertArrayHasKey('apiAlias', $members, 'The control removes a member the served body really carries.');

        $violations = $this->schemaViolations('/content/{path}', (object) array_diff_key($members, ['apiAlias' => null]));

        static::assertNotSame([], $violations);
        static::assertStringContainsString('[required]', implode("\n", $violations));
        static::assertStringContainsString('apiAlias', implode("\n", $violations));
    }

    /**
     * The decoded body of the route the path key names, proven to be the fixture's own page before anything is
     * asserted about its shape. The header and footer families reach their layout through section resolution
     * rather than through the URL, so without this a route resolving some other assignment would validate a
     * body this test never built and the row would pass for the wrong reason.
     */
    private function servedBody(string $schemaPath): \stdClass
    {
        $body = json_decode($this->requestBody($this->requestUri($schemaPath)), false, 512, \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $body);

        static::assertSame(
            $this->ids->get('layout'),
            get_object_vars($body)['id'] ?? null,
            \sprintf('"%s" must serve the layout this fixture bound to its section.', $schemaPath),
        );

        return $body;
    }

    /**
     * One entry per leaf validation failure, each naming the JSON pointer into the body, the schema keyword
     * that rejected it and the message that keyword produced — so a future divergence reads as a specific
     * contradiction rather than as "the body does not validate".
     *
     * @return list<string>
     */
    private function schemaViolations(string $schemaPath, mixed $body): array
    {
        $result = $this->validator()->validate($body, self::SCHEMA_DOCUMENT_URI . $this->declaredResponseSchemaRef($schemaPath));

        $error = $result->error();
        if ($error === null) {
            return [];
        }

        $formatter = new ErrorFormatter();

        /** @var array<string, list<string>> $keyed */
        $keyed = $formatter->formatKeyed(
            $error,
            static fn (ValidationError $leaf): string => \sprintf('[%s] %s', $leaf->keyword(), $formatter->formatErrorMessage($leaf)),
        );

        $violations = [];
        foreach ($keyed as $pointer => $messages) {
            foreach ($messages as $message) {
                $violations[] = $pointer . ' ' . $message;
            }
        }

        return $violations;
    }

    /**
     * The `$ref` the route's own 200 response entry declares. Reading it here rather than hard-coding a
     * component name is what ties the validation to the route: a route that stops declaring a JSON response
     * schema fails here instead of quietly validating against a schema nobody serves.
     */
    private function declaredResponseSchemaRef(string $schemaPath): string
    {
        $ref = $this->storeApiDocument()['paths'][$schemaPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'] ?? null;

        static::assertIsString($ref, \sprintf('The Store API document declares no JSON 200 response schema for "%s".', $schemaPath));
        static::assertStringStartsWith('#/', $ref);

        return $ref;
    }

    /**
     * Registers the assembled document's `components` node, which is what every `#/components/...` pointer in a
     * content schema resolves through — the page schemas reach their node schemas that way, and the node
     * schemas reach themselves recursively.
     */
    private function validator(): Validator
    {
        $document = $this->storeApiDocument();
        static::assertArrayHasKey('components', $document);

        $raw = Helper::toJSON(['components' => $document['components']]);
        static::assertIsObject($raw);

        $validator = new Validator(null, 20, false);

        $resolver = $validator->resolver();
        static::assertNotNull($resolver);
        static::assertTrue($resolver->registerRaw($raw, self::SCHEMA_DOCUMENT_URI));

        return $validator;
    }

    /**
     * @return array<string, mixed>
     */
    private function storeApiDocument(): array
    {
        if ($this->storeApiDocument !== null) {
            return $this->storeApiDocument;
        }

        $generator = $this->getContainer()->get(StoreApiGenerator::class);

        return $this->storeApiDocument = $generator->generate(
            $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );
    }

    /**
     * Derived from the path key the schema hangs off, so the request and the schema cannot drift apart. Only
     * the main family's keys carry `{path}`; the header and footer keys are already the whole route.
     */
    private function requestUri(string $schemaPath): string
    {
        if (!str_contains($schemaPath, '{path}')) {
            return '/store-api' . $schemaPath;
        }

        return '/store-api' . str_replace('{path}', 'category/' . $this->ids->get('category'), $schemaPath);
    }

    private function requestBody(string $uri): string
    {
        $this->browser->request('GET', $uri);

        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAsArray(string $content): array
    {
        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);

        return $body;
    }

    /**
     * A nested tree with element style on two nodes, a statically authored primitive value, and a loader-hydrated
     * media entity: every node carries at least one rendered property, so no element of this fixture stands in
     * for the empty case the other fixture exists to serve.
     */
    private function createPopulatedLayout(string $section): void
    {
        $this->createMedia();

        $this->persistLayout($section, [[
            'id' => $this->ids->get('root-grid'),
            'component' => 'Sw:Grid:Container',
            'properties' => [],
            'style' => ['col-span' => ['xs' => 6]],
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
     * Both empty cases in one layout. The single element declares no slots, carries no style, and holds an
     * authored null under its type's only declared primitive: the write-boundary default seeding skips a key
     * that is already present, and the render admits no key whose stored value is the null variant, so the
     * element renders with zero properties. Zero rendered properties across the whole page is also what leaves
     * the resolved-value index empty, which is what makes `data` and `assignments` empty maps.
     */
    private function createEmptyCaseLayout(string $section): void
    {
        $this->persistLayout($section, [[
            'id' => $this->ids->get('bare-text'),
            'component' => 'Sw:Content:Text',
            'properties' => ['text' => null],
        ]]);
    }

    /**
     * @param list<array<string, mixed>> $tree
     */
    private function persistLayout(string $section, array $tree): void
    {
        $context = Context::createDefaultContext();

        if ($section === self::SECTION_MAIN) {
            $this->createCategory();
        }

        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->get('layout'),
            'name' => self::LAYOUT_NAME,
            'version' => self::LAYOUT_VERSION,
            'rootSource' => self::ROOT_SOURCE_BY_SECTION[$section],
            'layout' => $tree,
        ]], $context);

        $this->repository(self::ASSIGNMENT_REPOSITORY_BY_SECTION[$section])
            ->create([$this->assignmentPayload($section)], $context);
    }

    /**
     * Main binds its layout to the category the request path names. Header and footer carry no path, so their
     * assignment is scoped to the browser's own sales channel with a null domain — the middle tier of the
     * domain-aware resolution, reached by the real resolver rather than around it.
     *
     * @return array<string, mixed>
     */
    private function assignmentPayload(string $section): array
    {
        if ($section === self::SECTION_MAIN) {
            return [
                'id' => $this->ids->get('assignment'),
                'categoryId' => $this->ids->get('category'),
                'salesChannelId' => null,
                'contentLayoutId' => $this->ids->get('layout'),
            ];
        }

        return [
            'id' => $this->ids->get('assignment'),
            'domainId' => null,
            'salesChannelId' => $this->getSalesChannelApiSalesChannelId(),
            'contentLayoutId' => $this->ids->get('layout'),
        ];
    }

    private function createCategory(): void
    {
        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Content route schema category',
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
