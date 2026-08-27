<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Api\AttachElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\BindElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Api\DuplicateElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\InsertElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Api\MoveElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\RemoveElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\ReplaceElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\UnwrapElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\WrapElementsRequest;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutMutationController::class)]
class LayoutMutationControllerTest extends TestCase
{
    #[TestDox('serializes the mutation result into the layout, resolutions, diagnostics and affected ids')]
    public function testInsertSerializesMutationResult(): void
    {
        $result = MutationResult::fromParts(new StoredTree([new StoredElement('el-1', 'Sw:Card')]), ['el-1' => []], new DiagnosticsReport([]), ['el-1']);
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
    #[DataProvider('dispatchesExpectedOpProvider')]
    #[TestDox('dispatches each route to the matching mutation op')]
    public function testRouteDispatchesExpectedOp(\Closure $invoke, string $expectedOp): void
    {
        $captured = null;
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturnCallback(function (LayoutMutation $mutation) use (&$captured): MutationResult {
            $captured = $mutation;

            return MutationResult::fromParts(new StoredTree([]), [], new DiagnosticsReport([]), []);
        });

        $invoke($this->controller($pipeline));

        static::assertInstanceOf($expectedOp, $captured);
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

    #[TestDox('threads the root source context resolved from the registry into the mutation pipeline')]
    public function testResolvesRootSource(): void
    {
        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: StoredElement::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
        )];

        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willReturnCallback(function (?string $rootSource, Context $context) use ($rootContext): array {
            static::assertSame('product', $rootSource);

            return $rootContext;
        });

        $threadedRootContext = false;
        $controller = $this->controller($this->capturingPipeline($threadedRootContext), $registry);

        $controller->insert(new InsertElementRequest('Sw:Card', rootSource: 'product'), Context::createDefaultContext());

        static::assertSame($rootContext, $threadedRootContext);
    }

    #[TestDox('threads a null context into the pipeline when the registry resolves no bound source')]
    public function testWithoutRootSourceThreadsNullContext(): void
    {
        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willReturn(null);

        $threadedRootContext = 'unset';
        $controller = $this->controller($this->capturingPipeline($threadedRootContext), $registry);

        $controller->insert(new InsertElementRequest('Sw:Card'), Context::createDefaultContext());

        static::assertNull($threadedRootContext);
    }

    #[TestDox('encodes an empty resolutions map as a JSON object, not an array')]
    public function testEmptyResolutionsEncodeAsJsonObject(): void
    {
        $result = MutationResult::fromParts(new StoredTree([]), [], new DiagnosticsReport([]), []);
        $controller = $this->controller($this->pipelineReturning($result));

        $response = $controller->remove(new RemoveElementRequest('el'), Context::createDefaultContext());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('"resolutions":{}', $content);
    }

    #[TestDox('propagates the registry unknownRootSource exception instead of reaching the pipeline')]
    public function testRejectsUnknownRootSource(): void
    {
        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willThrowException(
            ContentSystemException::unknownRootSource('definitely-not-a-root-source')
        );

        $controller = $this->controller(rootSourceRegistry: $registry);

        try {
            $controller->insert(new InsertElementRequest('Sw:Card', rootSource: 'definitely-not-a-root-source'), Context::createDefaultContext());
            static::fail('Expected a ContentSystemException for the unknown root source.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $exception->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{\Closure(LayoutMutationController): Response, class-string<LayoutMutation>}>
     */
    public static function dispatchesExpectedOpProvider(): iterable
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
        yield 'bind' => [static fn (LayoutMutationController $c): Response => $c->bind(new BindElementRequest('el', 'source:spec'), $context), BindElement::class];
    }

    /**
     * @return iterable<string, array{MutationResult, string, \Closure(mixed): mixed, mixed}>
     */
    public static function replaceOptionalFieldsProvider(): iterable
    {
        yield 'orphaned subtrees surface for re-attachment' => [
            MutationResult::fromParts(new StoredTree([new StoredElement('el', 'Sw:New')]), [], new DiagnosticsReport([]), ['el'], [new StoredElement('orphan', 'Sw:Block')]),
            'orphaned',
            static fn (mixed $value): mixed => $value[0]['id'],
            'orphan',
        ];

        yield 'dropped wiring keys are reported' => [
            MutationResult::fromParts(new StoredTree([new StoredElement('el', 'Sw:New')]), [], new DiagnosticsReport([]), ['el'], [], ['legacy']),
            'droppedWiring',
            static fn (mixed $value): mixed => $value,
            ['legacy'],
        ];

        yield 'dropped property values are reported' => [
            MutationResult::fromParts(new StoredTree([new StoredElement('el', 'Sw:New')]), [], new DiagnosticsReport([]), ['el'], [], [], ['headline' => StoredValue::ofString('Old headline')]),
            'droppedProperties',
            static fn (mixed $value): mixed => $value['headline'],
            'Old headline',
        ];
    }

    private function controller(
        ?MutationPipeline $pipeline = null,
        ?RootSourceRegistry $rootSourceRegistry = null,
    ): LayoutMutationController {
        return new LayoutMutationController(
            $this->decoder(),
            $pipeline ?? $this->pipelineReturning(MutationResult::fromParts(new StoredTree([]), [], new DiagnosticsReport([]), [])),
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $rootSourceRegistry ?? static::createStub(RootSourceRegistry::class),
            $this->elementCodec(),
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            // BindingApplicator is final: a real instance over a stubbed serializer provider.
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class)),
        );
    }

    /**
     * Builds a pipeline stub that captures the root context threaded into run() so a test can assert what the
     * controller resolved and passed through.
     */
    private function capturingPipeline(mixed &$captured): MutationPipeline
    {
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturnCallback(
            function (LayoutMutation $mutation, StoredTree $tree, ?array $analyzedRootContext) use (&$captured): MutationResult {
                $captured = $analyzedRootContext;

                return MutationResult::fromParts(new StoredTree([]), [], new DiagnosticsReport([]), []);
            }
        );

        return $pipeline;
    }

    private function decoder(): DraftLayoutDecoder
    {
        return new DraftLayoutDecoder(
            $this->elementCodec(),
            new StoredTreeStyleNormalizer(
                new ElementStyleNormalizer(static::createStub(AbstractContentSystemStyleOptionRegistry::class), new BoxSpacingNormalizer())
            ),
            new ViolationConstraintMapper(),
        );
    }

    private function pipelineReturning(MutationResult $result): MutationPipeline
    {
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturn($result);

        return $pipeline;
    }

    private function elementCodec(): StoredElementCodec
    {
        return new StoredElementCodec(static::createStub(DataLoaderConfigSerializerProvider::class));
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
