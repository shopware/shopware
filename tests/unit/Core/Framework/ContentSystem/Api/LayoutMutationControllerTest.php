<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Api\AttachElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Api\DuplicateElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\InsertElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Api\MoveElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\RemoveElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\ReplaceElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\SpecificationSourceLocator;
use Shopware\Core\Framework\ContentSystem\Api\UnwrapElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\WrapElementsRequest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(LayoutMutationController::class)]
class LayoutMutationControllerTest extends TestCase
{
    #[TestDox('serializes the mutation result into the layout, resolutions, diagnostics and affected ids')]
    public function testInsertSerializesMutationResult(): void
    {
        $result = new MutationResult([new ContentElement('el-1', 'Sw:Card')], ['el-1' => []], new DiagnosticsReport([]), ['el-1']);
        $controller = $this->controller($this->pipelineReturning($result));

        $response = $controller->insert(new InsertElementRequest('Sw:Card'), Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->decode($response);
        static::assertSame('el-1', $body['layout'][0]['id']);
        static::assertSame(['el-1'], $body['affectedElementIds']);
        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertArrayHasKey('el-1', $body['resolutions']);
    }

    /**
     * @param \Closure(LayoutMutationController): Response $invoke
     * @param class-string<LayoutMutation> $expectedOp
     */
    #[DataProvider('routeDispatchProvider')]
    #[TestDox('dispatches each route to the matching mutation op')]
    public function testRouteDispatchesExpectedOp(\Closure $invoke, string $expectedOp): void
    {
        $captured = null;
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturnCallback(function (LayoutMutation $mutation) use (&$captured): MutationResult {
            $captured = $mutation;

            return new MutationResult([], [], new DiagnosticsReport([]), []);
        });

        $invoke($this->controller($pipeline));

        static::assertInstanceOf($expectedOp, $captured);
    }

    /**
     * @return iterable<string, array{\Closure(LayoutMutationController): Response, class-string<LayoutMutation>}>
     */
    public static function routeDispatchProvider(): iterable
    {
        $context = Context::createDefaultContext();

        yield 'insert' => [static fn (LayoutMutationController $c): Response => $c->insert(new InsertElementRequest('Sw:Card'), $context), InsertElement::class];
        yield 'remove' => [static fn (LayoutMutationController $c): Response => $c->remove(new RemoveElementRequest('el'), $context), RemoveElement::class];
        yield 'move' => [static fn (LayoutMutationController $c): Response => $c->move(new MoveElementRequest('el'), $context), MoveElement::class];
        yield 'replace' => [static fn (LayoutMutationController $c): Response => $c->replace(new ReplaceElementRequest('el', 'Sw:New'), $context), ReplaceElement::class];
        yield 'duplicate' => [static fn (LayoutMutationController $c): Response => $c->duplicate(new DuplicateElementRequest('el'), $context), DuplicateElement::class];
        yield 'wrap' => [static fn (LayoutMutationController $c): Response => $c->wrap(new WrapElementsRequest(['a'], 'Sw:Container'), $context), WrapElements::class];
        yield 'unwrap' => [static fn (LayoutMutationController $c): Response => $c->unwrap(new UnwrapElementRequest('el'), $context), UnwrapElement::class];
        yield 'attach' => [static fn (LayoutMutationController $c): Response => $c->attach(new AttachElementRequest(['id' => 'incoming', 'component' => 'Sw:Card']), $context), AttachElement::class];
    }

    /**
     * @param \Closure(mixed): mixed $accessor
     */
    #[DataProvider('replaceOptionalFieldsProvider')]
    #[TestDox('serializes the populated optional replace fields in the response')]
    public function testReplaceSerializesOptionalFields(MutationResult $result, string $field, \Closure $accessor, mixed $expected): void
    {
        $controller = $this->controller($this->pipelineReturning($result));

        $response = $controller->replace(new ReplaceElementRequest('el', 'Sw:New'), Context::createDefaultContext());

        static::assertSame($expected, $accessor($this->decode($response)[$field]));
    }

    /**
     * @return iterable<string, array{MutationResult, string, \Closure(mixed): mixed, mixed}>
     */
    public static function replaceOptionalFieldsProvider(): iterable
    {
        yield 'orphaned subtrees surface for re-attachment' => [
            new MutationResult([new ContentElement('el', 'Sw:New')], [], new DiagnosticsReport([]), ['el'], [new ContentElement('orphan', 'Sw:Block')]),
            'orphaned',
            static fn (mixed $value): mixed => $value[0]['id'],
            'orphan',
        ];

        yield 'dropped wiring keys are reported' => [
            new MutationResult([new ContentElement('el', 'Sw:New')], [], new DiagnosticsReport([]), ['el'], [], ['legacy']),
            'droppedWiring',
            static fn (mixed $value): mixed => $value,
            ['legacy'],
        ];

        yield 'dropped property values are reported' => [
            new MutationResult([new ContentElement('el', 'Sw:New')], [], new DiagnosticsReport([]), ['el'], [], [], ['headline' => 'Old headline']),
            'droppedProperties',
            static fn (mixed $value): mixed => $value['headline'],
            'Old headline',
        ];
    }

    #[TestDox('threads the entityType source root context into the mutation pipeline')]
    public function testResolvesEntityTypeSource(): void
    {
        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: ContentElement::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
        )];

        $source = static::createStub(AbstractSpecificationSource::class);
        $source->method('providedRootContext')->willReturn($rootContext);

        $sourceLocator = static::createStub(SpecificationSourceLocator::class);
        $sourceLocator->method('resolveByEntityType')->willReturn($source);

        $threadedRootContext = false;
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturnCallback(
            function (LayoutMutation $mutation, array $tree, ?array $analyzedRootContext) use (&$threadedRootContext): MutationResult {
                $threadedRootContext = $analyzedRootContext;

                return new MutationResult([], [], new DiagnosticsReport([]), []);
            }
        );

        $controller = $this->controller($pipeline, $sourceLocator);

        $controller->insert(new InsertElementRequest('Sw:Card', entityType: 'product'), Context::createDefaultContext());

        static::assertSame($rootContext, $threadedRootContext);
    }

    #[TestDox('threads the section source root context into the mutation pipeline')]
    public function testResolvesSectionSource(): void
    {
        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: ContentElement::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
        )];

        $source = static::createStub(AbstractSpecificationSource::class);
        $source->method('providedRootContext')->willReturn($rootContext);

        $sourceLocator = static::createStub(SpecificationSourceLocator::class);
        $sourceLocator->method('resolveBySection')->willReturn($source);

        $threadedRootContext = false;
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturnCallback(
            function (LayoutMutation $mutation, array $tree, ?array $analyzedRootContext) use (&$threadedRootContext): MutationResult {
                $threadedRootContext = $analyzedRootContext;

                return new MutationResult([], [], new DiagnosticsReport([]), []);
            }
        );

        $controller = $this->controller($pipeline, $sourceLocator);

        $controller->insert(new InsertElementRequest('Sw:Card', section: 'header'), Context::createDefaultContext());

        static::assertSame($rootContext, $threadedRootContext);
    }

    #[TestDox('encodes an empty resolutions map as a JSON object, not an array')]
    public function testEmptyResolutionsEncodeAsJsonObject(): void
    {
        $result = new MutationResult([], [], new DiagnosticsReport([]), []);
        $controller = $this->controller($this->pipelineReturning($result));

        $response = $controller->remove(new RemoveElementRequest('el'), Context::createDefaultContext());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('"resolutions":{}', $content);
    }

    #[TestDox('rejects a section value with no matching ContentSection with a 400')]
    public function testRejectsUnknownSection(): void
    {
        $controller = $this->controller();

        $this->expectExceptionObject(ContentSystemException::noSourceForSection('does-not-exist'));

        $controller->insert(new InsertElementRequest('Sw:Card', section: 'does-not-exist'), Context::createDefaultContext());
    }

    private function controller(
        ?MutationPipeline $pipeline = null,
        ?SpecificationSourceLocator $sourceLocator = null,
    ): LayoutMutationController {
        return new LayoutMutationController(
            $this->decoder(),
            $pipeline ?? $this->pipelineReturning(new MutationResult([], [], new DiagnosticsReport([]), [])),
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $sourceLocator ?? static::createStub(SpecificationSourceLocator::class),
            $this->elementSerializer(),
        );
    }

    private function decoder(): DraftLayoutDecoder
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturnCallback(
            static fn (array $raw): ContentElement => new ContentElement(
                \is_string($raw['id'] ?? null) ? $raw['id'] : 'incoming',
                \is_string($raw['component'] ?? null) ? $raw['component'] : 'Sw:Card',
            ),
        );

        return new DraftLayoutDecoder($serializer);
    }

    private function pipelineReturning(MutationResult $result): MutationPipeline
    {
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturn($result);

        return $pipeline;
    }

    private function elementSerializer(): ContentElementFieldSerializer
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('serializeContentElement')->willReturnCallback(
            static fn (ContentElement $element): array => ['id' => $element->getId(), 'component' => $element->getComponent(), 'properties' => []],
        );

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
