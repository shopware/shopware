<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Api\ContentDiagnoseController;
use Shopware\Core\Framework\ContentSystem\Api\ContentDiagnoseRequest;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ContentDiagnoseController::class)]
class ContentDiagnoseControllerTest extends TestCase
{
    #[TestDox('returns the per-element resolutions for a well-formed tree without a root source')]
    public function testDiagnoseReturnsPerElementResolutions(): void
    {
        $analysis = new LayoutAnalysis(
            new DiagnosticsReport([]),
            ['el-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')]],
        );

        $controller = $this->controller(
            diagnostics: $this->diagnosticsReturning($analysis),
            serializer: $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
        );

        $response = $controller->diagnose(new ContentDiagnoseRequest([['id' => 'el-1', 'component' => 'Sw:Block']]), Context::createDefaultContext());

        static::assertSame('headline', $this->decode($response)['resolutions']['el-1'][0]['key']);
    }

    #[TestDox('reports a not-well-formed verdict that merges analysis violations into the diagnostics report')]
    public function testDiagnoseMergesAnalysisViolationsIntoWellFormednessVerdict(): void
    {
        $analysis = new LayoutAnalysis(
            new DiagnosticsReport([new Violation(ViolationCode::DuplicateElementId, 'el-1', null, 'dup')]),
            [],
        );

        $controller = $this->controller(
            diagnostics: $this->diagnosticsReturning($analysis),
            serializer: $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
        );

        $response = $controller->diagnose(new ContentDiagnoseRequest([['id' => 'el-1', 'component' => 'Sw:Block']]), Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->decode($response);
        static::assertFalse($body['diagnostics']['wellFormed']);
        static::assertSame(ViolationCode::DuplicateElementId->value, $body['diagnostics']['violations'][0]['code']);
    }

    #[TestDox('maps a per-element decode client-defect to an invalid_config diagnostic without failing the request')]
    public function testDiagnoseMapsDecodeClientDefect(): void
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $controller = $this->controller(
            diagnostics: $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])),
            serializer: $serializer,
        );

        $response = $controller->diagnose(new ContentDiagnoseRequest([['id' => 'el-1', 'component' => 'Sw:Block']]), Context::createDefaultContext());

        $body = $this->decode($response);
        static::assertFalse($body['diagnostics']['wellFormed']);
        static::assertSame(ViolationCode::InvalidConfig->value, $body['diagnostics']['violations'][0]['code']);
    }

    #[TestDox('threads the root source context resolved from the registry into the diagnostics analysis')]
    public function testDiagnoseResolvesRootSource(): void
    {
        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: ContentElement::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
        )];

        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willReturn($rootContext);

        $threadedRootContext = false;
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturnCallback(
            function (array $tree, ?array $analyzedRootContext) use (&$threadedRootContext): LayoutAnalysis {
                $threadedRootContext = $analyzedRootContext;

                return new LayoutAnalysis(new DiagnosticsReport([]), []);
            }
        );

        $controller = $this->controller(
            diagnostics: $diagnostics,
            serializer: $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
            rootSourceRegistry: $registry,
        );

        $controller->diagnose(
            new ContentDiagnoseRequest([['id' => 'el-1', 'component' => 'Sw:Block']], rootSource: 'product'),
            Context::createDefaultContext(),
        );

        static::assertSame($rootContext, $threadedRootContext);
    }

    #[TestDox('threads a null root context into the analysis when the registry resolves no bound source')]
    public function testDiagnoseWithoutRootSourceThreadsNullContext(): void
    {
        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willReturn(null);

        $threadedRootContext = 'unset';
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturnCallback(
            function (array $tree, ?array $analyzedRootContext) use (&$threadedRootContext): LayoutAnalysis {
                $threadedRootContext = $analyzedRootContext;

                return new LayoutAnalysis(new DiagnosticsReport([]), []);
            }
        );

        $controller = $this->controller(
            diagnostics: $diagnostics,
            serializer: $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
            rootSourceRegistry: $registry,
        );

        $controller->diagnose(new ContentDiagnoseRequest([['id' => 'el-1', 'component' => 'Sw:Block']]), Context::createDefaultContext());

        static::assertNull($threadedRootContext);
    }

    #[TestDox('propagates the registry unknownRootSource exception instead of swallowing it into a 200')]
    public function testDiagnoseRejectsUnknownRootSource(): void
    {
        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willThrowException(
            ContentSystemException::unknownRootSource('definitely-not-a-root-source')
        );

        $controller = $this->controller(
            diagnostics: $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])),
            serializer: $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
            rootSourceRegistry: $registry,
        );

        try {
            $controller->diagnose(
                new ContentDiagnoseRequest([['id' => 'el-1', 'component' => 'Sw:Block']], rootSource: 'definitely-not-a-root-source'),
                Context::createDefaultContext(),
            );
            static::fail('Expected a ContentSystemException for the unknown root source.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $exception->getErrorCode());
        }
    }

    #[TestDox('rejects a structurally invalid element with a 400')]
    public function testDiagnoseRejectsStructurallyInvalidElement(): void
    {
        $controller = $this->controller(
            diagnostics: $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])),
            serializer: static::createStub(ContentElementFieldSerializer::class),
        );

        try {
            $controller->diagnose(new ContentDiagnoseRequest([['component' => 'Sw:Block']]), Context::createDefaultContext());
            static::fail('Expected a ContentSystemException for the structurally invalid element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    private function controller(
        LayoutDiagnostics $diagnostics,
        ContentElementFieldSerializer $serializer,
        ?RootSourceRegistry $rootSourceRegistry = null,
    ): ContentDiagnoseController {
        return new ContentDiagnoseController(
            new DraftLayoutDecoder($serializer),
            $diagnostics,
            $rootSourceRegistry ?? static::createStub(RootSourceRegistry::class),
        );
    }

    private function diagnosticsReturning(LayoutAnalysis $analysis): LayoutDiagnostics
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn($analysis);

        return $diagnostics;
    }

    private function serializerDecoding(ContentElement $element): ContentElementFieldSerializer
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturn($element);

        return $serializer;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $content = $response->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
