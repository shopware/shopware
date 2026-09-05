<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentDiagnoseControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const DIAGNOSE_URL = '/api/_action/content-system/layout/diagnose';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('reports a well-formed verdict for a layout of registered components without a bound source')]
    public function testDiagnoseReportsWellFormed(): void
    {
        $body = $this->diagnose(['layout' => [$this->element($this->registeredComponent())]]);

        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertArrayHasKey('resolutions', $body);
    }

    #[TestDox('reports an unregistered component as an intrinsic violation without persisting')]
    public function testDiagnoseReportsUnregisteredComponent(): void
    {
        $body = $this->diagnose(['layout' => [$this->element('Sw:Test:DefinitelyUnregistered')]]);

        static::assertFalse($body['diagnostics']['wellFormed']);
        $codes = array_column($body['diagnostics']['violations'], 'code');
        static::assertContains('unregistered_component', $codes);
    }

    #[TestDox('reports an unregistered style option as an unknown_style_option violation keyed on the option name')]
    public function testDiagnoseReportsUnknownStyleOption(): void
    {
        $element = $this->element($this->registeredComponent());
        $element['style'] = ['definitely-not-a-style-option' => ['xs' => 'x']];

        $body = $this->diagnose(['layout' => [$element]]);

        static::assertFalse($body['diagnostics']['wellFormed']);

        $violations = array_values(array_filter(
            $body['diagnostics']['violations'],
            static fn (array $violation): bool => $violation['code'] === 'unknown_style_option',
        ));

        static::assertCount(1, $violations);
        static::assertSame('intrinsic', $violations[0]['scope']);
        static::assertSame('error', $violations[0]['severity']);
        static::assertSame('definitely-not-a-style-option', $violations[0]['key']);
    }

    /**
     * The element-local client defects reach the route as catalogued client-defect codes, so the diagnose body
     * reports each on the offending element instead of the request failing: the codec throws on decode, and the
     * lintable decode collects a catalogued code as an `invalid_config` violation in a 200 body.
     *
     * The layout carries a well-formed sibling beside the defective element. Without it the attribution and the
     * count assert nothing: one root yields at most one caught exception, and the lintable decode attributes to
     * the id the pre-decode gate read off that same and only element, so both would hold whatever the route did.
     *
     * @param array<string, mixed> $defect
     */
    #[DataProvider('elementLocalClientDefectProvider')]
    #[TestDox('reports $_dataName as an invalid_config violation attributed to the offending element')]
    public function testDiagnoseReportsAnElementLocalClientDefect(array $defect, string $expectedMessage): void
    {
        $elementId = $this->ids->get('element');

        $body = $this->diagnose(['layout' => [
            [
                'id' => $elementId,
                'component' => $this->registeredComponent(),
                'properties' => [],
                ...$defect,
            ],
            [
                'id' => $this->ids->get('well-formed-sibling'),
                'component' => $this->registeredComponent(),
                'properties' => [],
            ],
        ]]);

        static::assertFalse($body['diagnostics']['wellFormed']);

        $violations = array_values(array_filter(
            $body['diagnostics']['violations'],
            static fn (array $violation): bool => $violation['code'] === 'invalid_config',
        ));

        static::assertCount(1, $violations);
        static::assertSame($elementId, $violations[0]['elementId']);
        static::assertSame($expectedMessage, $violations[0]['message']);
    }

    #[TestDox('resolves the root source from the rootSource field and returns a resolvability verdict')]
    public function testDiagnoseWithRootSource(): void
    {
        $body = $this->diagnose([
            'layout' => [$this->element($this->registeredComponent())],
            'rootSource' => 'product',
        ]);

        static::assertArrayHasKey('resolvable', $body['diagnostics']);
    }

    #[TestDox('serves a root-ambient candidate as origin root with a null provider element id at every depth, beside the ancestor-provided parent candidate')]
    public function testDiagnoseServesRootAndParentOriginsForANestedElement(): void
    {
        // Sw:Product:PriceDisplay declares a SalesChannelProductEntity `product` property, and the `product`
        // root source supplies exactly that FQCN as root-ambient context, so both elements are offered it. The
        // root element takes that ambient value through its own root-scoped consumer, which is what backs the
        // `product` it provides downstream and puts a second, element-addressed candidate beside the ambient
        // one on the child. Without that consumer nothing would deliver a value into the root element at
        // render, and its provider would be exposed to no one.
        $rootId = $this->ids->get('provider-root');
        $childId = $this->ids->get('nested-child');

        $body = $this->diagnose([
            'rootSource' => 'product',
            'layout' => [[
                'id' => $rootId,
                'component' => 'Sw:Product:PriceDisplay',
                'properties' => [],
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'scope' => 'root']],
                'slots' => ['content' => [[
                    'id' => $childId,
                    'component' => 'Sw:Product:PriceDisplay',
                    'properties' => [],
                ]]],
            ]],
        ]);

        $topLevelRoots = $this->candidatesWithOrigin($this->productResolution($body, $rootId), 'root');
        $nested = $this->productResolution($body, $childId);
        $nestedRoots = $this->candidatesWithOrigin($nested, 'root');
        $nestedParents = $this->candidatesWithOrigin($nested, 'parent');

        // Depth-independence: the ambient offer reaches the nested element with no wiring in between, and it
        // carries no provider address, because no element supplies it.
        static::assertCount(1, $topLevelRoots);
        static::assertNull($topLevelRoots[0]['providerElementId']);
        static::assertCount(1, $nestedRoots);
        static::assertNull($nestedRoots[0]['providerElementId']);
        static::assertSame('product', $nestedRoots[0]['contextKey']);

        // Element-provided context keeps the `parent` origin and names the ancestor that provides it.
        static::assertCount(1, $nestedParents);
        static::assertSame($rootId, $nestedParents[0]['providerElementId']);

        // Root outranks Parent in the default pick.
        static::assertSame('root', $nested['resolved']['origin']);
    }

    #[TestDox('treats an empty rootSource as absent and reports intrinsic well-formedness without gating')]
    public function testDiagnoseTreatsEmptyRootSourceAsAbsent(): void
    {
        $body = $this->diagnose([
            'layout' => [$this->element($this->registeredComponent())],
            'rootSource' => '',
        ]);

        static::assertTrue($body['diagnostics']['wellFormed']);
    }

    #[TestDox('rejects an unknown rootSource with a 400 and the unknownRootSource code, never reaching resolve')]
    public function testDiagnoseRejectsUnknownRootSource(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::DIAGNOSE_URL, [
            'layout' => [$this->element($this->registeredComponent())],
            'rootSource' => 'definitely-not-a-root-source',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_ROOT_SOURCE, array_column($body['errors'], 'code'));
    }

    #[TestDox('rejects an unknown request field with a 400 and the unknownRequestField code')]
    public function testDiagnoseRejectsUnknownRequestField(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::DIAGNOSE_URL, [
            'layout' => [$this->element($this->registeredComponent())],
            'entityType' => 'product',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_REQUEST_FIELD, array_column($body['errors'], 'code'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function elementLocalClientDefectProvider(): iterable
    {
        yield 'a numeric wiring key' => [
            ['properties' => [1 => 'x']],
            'Element property map key must be string, got int',
        ];

        yield 'two consumers sharing one base key' => [
            ['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => false],
                'category' => ['type' => 'single', 'required' => false, 'propertyAlias' => 'product'],
            ]],
            'Property key "product" is used by both context "product" and "category". Each propertyAlias must be unique within an element.',
        ];

        yield 'a redistributing consumer keyed by a dotted path' => [
            ['acceptsContext' => [
                'product.manufacturer' => ['type' => 'single', 'required' => false, 'redistribute' => true],
            ]],
            'Context key "product.manufacturer" uses dot notation and cannot be redistributed. Only base keys support redistribution.',
        ];

        yield 'a redistributing consumer whose derived key an authored provider holds' => [
            [
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => false, 'redistribute' => true]],
            ],
            'Context key "product" has both redistribute:true and explicit providesContext. Use one or the other.',
        ];
    }

    /**
     * The `product` property resolution of one element, off the diagnose body's resolutions map.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function productResolution(array $body, string $elementId): array
    {
        static::assertIsArray($body['resolutions']);
        static::assertArrayHasKey($elementId, $body['resolutions']);
        static::assertIsArray($body['resolutions'][$elementId]);

        $matches = array_values(array_filter(
            $body['resolutions'][$elementId],
            static fn (array $resolution): bool => $resolution['key'] === 'product',
        ));

        static::assertCount(1, $matches);

        return $matches[0];
    }

    /**
     * @param array<string, mixed> $resolution
     *
     * @return list<array<string, mixed>>
     */
    private function candidatesWithOrigin(array $resolution, string $origin): array
    {
        static::assertIsArray($resolution['candidates']);

        return array_values(array_filter(
            $resolution['candidates'],
            static fn (array $candidate): bool => $candidate['origin'] === $origin,
        ));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function diagnose(array $payload): array
    {
        $this->getBrowser()->jsonRequest('POST', self::DIAGNOSE_URL, $payload);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function element(string $component): array
    {
        return ['id' => $this->ids->get('element'), 'component' => $component, 'properties' => []];
    }

    private function registeredComponent(): string
    {
        $types = $this->getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $name = array_key_first($types);
        static::assertIsString($name);

        return $name;
    }
}
