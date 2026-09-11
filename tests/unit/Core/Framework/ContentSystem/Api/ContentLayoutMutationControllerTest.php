<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutAttachRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutBindRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutDuplicateRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutInsertRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutMoveRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutRemoveRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutReplaceRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutUnwrapRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutWrapElementsRequest;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
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
use Shopware\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutMutationController::class)]
class ContentLayoutMutationControllerTest extends TestCase
{
    #[TestDox('serializes the persisted mutation result into the layout, resolutions, diagnostics and affected ids')]
    public function testInsertSerializesMutationResult(): void
    {
        $result = MutationResult::fromParts(new StoredTree([new StoredElement('el-1', 'Sw:Card')]), ['el-1' => []], new DiagnosticsReport([]), ['el-1']);
        $controller = $this->controller($this->mutatorReturning($result));

        $response = $controller->insert('layout-1', new ContentLayoutInsertRequest('Sw:Card', null), Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->decode($response);
        static::assertSame('el-1', $body['layout'][0]['id']);
        static::assertSame(['el-1'], $body['affectedElementIds']);
        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertArrayHasKey('el-1', $body['resolutions']);
    }

    #[TestDox('passes the path layout id and expected version token through to the mutator')]
    public function testPassesLayoutIdAndExpectedVersionToMutator(): void
    {
        $capturedId = null;
        $capturedVersion = false;
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturnCallback(
            function (string $layoutId, ?string $expectedVersion) use (&$capturedId, &$capturedVersion): MutationResult {
                $capturedId = $layoutId;
                $capturedVersion = $expectedVersion;

                return MutationResult::fromParts(new StoredTree([]), [], new DiagnosticsReport([]), []);
            }
        );

        $this->controller($mutator)->remove('layout-42', new ContentLayoutRemoveRequest('el', '2026-06-22T10:00:00.000+00:00'), Context::createDefaultContext());

        static::assertSame('layout-42', $capturedId);
        static::assertSame('2026-06-22T10:00:00.000+00:00', $capturedVersion);
    }

    /**
     * @param \Closure(ContentLayoutMutationController): Response $invoke
     * @param class-string<LayoutMutation> $expectedOp
     */
    #[DataProvider('dispatchesExpectedOpProvider')]
    #[TestDox('dispatches each route to the matching mutation op')]
    public function testRouteDispatchesExpectedOp(\Closure $invoke, string $expectedOp): void
    {
        $captured = null;
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturnCallback(
            function (string $layoutId, ?string $expectedVersion, LayoutMutation $mutation) use (&$captured): MutationResult {
                $captured = $mutation;

                return MutationResult::fromParts(new StoredTree([]), [], new DiagnosticsReport([]), []);
            }
        );

        $invoke($this->controller($mutator));

        static::assertInstanceOf($expectedOp, $captured);
    }

    /**
     * @return iterable<string, array{\Closure(ContentLayoutMutationController): Response, class-string<LayoutMutation>}>
     */
    public static function dispatchesExpectedOpProvider(): iterable
    {
        $context = Context::createDefaultContext();

        yield 'insert' => [static fn (ContentLayoutMutationController $c): Response => $c->insert('l', new ContentLayoutInsertRequest('Sw:Card', null), $context), InsertElement::class];
        yield 'remove' => [static fn (ContentLayoutMutationController $c): Response => $c->remove('l', new ContentLayoutRemoveRequest('el', null), $context), RemoveElement::class];
        yield 'move' => [static fn (ContentLayoutMutationController $c): Response => $c->move('l', new ContentLayoutMoveRequest('el', null), $context), MoveElement::class];
        yield 'replace' => [static fn (ContentLayoutMutationController $c): Response => $c->replace('l', new ContentLayoutReplaceRequest('el', 'Sw:New', null), $context), ReplaceElement::class];
        yield 'duplicate' => [static fn (ContentLayoutMutationController $c): Response => $c->duplicate('l', new ContentLayoutDuplicateRequest('el', null), $context), DuplicateElement::class];
        yield 'wrap' => [static fn (ContentLayoutMutationController $c): Response => $c->wrap('l', new ContentLayoutWrapElementsRequest(['a'], 'Sw:Container', null), $context), WrapElements::class];
        yield 'unwrap' => [static fn (ContentLayoutMutationController $c): Response => $c->unwrap('l', new ContentLayoutUnwrapRequest('el', null), $context), UnwrapElement::class];
        yield 'attach' => [static fn (ContentLayoutMutationController $c): Response => $c->attach('l', new ContentLayoutAttachRequest(['id' => 'incoming', 'component' => 'Sw:Card'], null), $context), AttachElement::class];
        yield 'bind' => [static fn (ContentLayoutMutationController $c): Response => $c->bind('l', new ContentLayoutBindRequest('el', 'core:hero', null), $context), BindElement::class];
    }

    /**
     * @param \Closure(array<string, mixed>): void $assert
     */
    #[DataProvider('serializesOptionalReplaceFieldsProvider')]
    #[TestDox('serializes the populated optional replace fields in the persisted response')]
    public function testReplaceSerializesOptionalFields(MutationResult $result, \Closure $assert): void
    {
        $controller = $this->controller($this->mutatorReturning($result));

        $response = $controller->replace('layout-1', new ContentLayoutReplaceRequest('el', 'Sw:New', null), Context::createDefaultContext());

        $assert($this->decode($response));
    }

    /**
     * @return iterable<string, array{MutationResult, \Closure(array<string, mixed>): void}>
     */
    public static function serializesOptionalReplaceFieldsProvider(): iterable
    {
        yield 'orphaned subtrees surface for re-attachment' => [
            MutationResult::fromParts(new StoredTree([new StoredElement('el', 'Sw:New')]), [], new DiagnosticsReport([]), ['el'], [new StoredElement('orphan', 'Sw:Block')]),
            static function (array $body): void {
                static::assertSame('orphan', $body['orphaned'][0]['id']);
            },
        ];

        yield 'dropped wiring keys are reported' => [
            MutationResult::fromParts(new StoredTree([new StoredElement('el', 'Sw:New')]), [], new DiagnosticsReport([]), ['el'], [], ['legacy']),
            static function (array $body): void {
                static::assertSame(['legacy'], $body['droppedWiring']);
            },
        ];

        yield 'dropped property values are reported' => [
            MutationResult::fromParts(new StoredTree([new StoredElement('el', 'Sw:New')]), [], new DiagnosticsReport([]), ['el'], [], [], ['headline' => StoredValue::ofString('Old headline')]),
            static function (array $body): void {
                static::assertSame('Old headline', $body['droppedProperties']['headline']);
            },
        ];
    }

    #[TestDox('encodes an empty resolutions map as a JSON object, not an array')]
    public function testEmptyResolutionsEncodeAsJsonObject(): void
    {
        $controller = $this->controller($this->mutatorReturning(MutationResult::fromParts(new StoredTree([]), [], new DiagnosticsReport([]), [])));

        $response = $controller->remove('layout-1', new ContentLayoutRemoveRequest('el', null), Context::createDefaultContext());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('"resolutions":{}', $content);
    }

    #[DataProvider('propagatesMutatorExceptionProvider')]
    #[TestDox('propagates the mutator exception unchanged: $_dataName')]
    public function testMutatePropagatesMutatorException(
        ContentSystemException $thrown,
        string $layoutId,
        ?string $expectedVersion,
        string $expectedErrorCode,
        int $expectedStatus,
    ): void {
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willThrowException($thrown);

        try {
            $this->controller($mutator)->remove($layoutId, new ContentLayoutRemoveRequest('el', $expectedVersion), Context::createDefaultContext());
            static::fail('Expected a ' . $expectedErrorCode . ' exception, but none was thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame($expectedErrorCode, $exception->getErrorCode());
            static::assertSame($expectedStatus, $exception->getStatusCode());
        }
    }

    /**
     * @return iterable<string, array{ContentSystemException, string, string|null, string, int}>
     */
    public static function propagatesMutatorExceptionProvider(): iterable
    {
        yield 'contentLayoutNotFound for an unknown layout id' => [
            ContentSystemException::contentLayoutNotFound('layout-404'),
            'layout-404',
            null,
            ContentSystemException::CONTENT_LAYOUT_NOT_FOUND,
            Response::HTTP_NOT_FOUND,
        ];

        yield 'layoutVersionConflict for a stale expected version token' => [
            ContentSystemException::layoutVersionConflict('layout-1'),
            'layout-1',
            '2020-01-01T00:00:00.000+00:00',
            ContentSystemException::LAYOUT_VERSION_CONFLICT,
            Response::HTTP_CONFLICT,
        ];
    }

    #[TestDox('rejects a malformed attach element with invalidLayoutStructure before the mutator is invoked')]
    public function testAttachThrowsWhenElementStructureIsInvalid(): void
    {
        $mutator = static::createStub(PersistedLayoutMutator::class);

        try {
            $this->controller($mutator)->attach('layout-1', new ContentLayoutAttachRequest(['component' => 'Sw:Card'], null), Context::createDefaultContext());
            static::fail('Expected a ' . ContentSystemException::INVALID_LAYOUT_STRUCTURE . ' exception, but none was thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }

    private function controller(PersistedLayoutMutator $mutator): ContentLayoutMutationController
    {
        return new ContentLayoutMutationController(
            $mutator,
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $this->elementCodec(),
            $this->decoder(),
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            // BindingApplicator is final: a real instance over a stubbed serializer provider.
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class), static::createStub(AbstractContentSystemElementTypeRegistry::class)),
        );
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

    private function mutatorReturning(MutationResult $result): PersistedLayoutMutator
    {
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturn($result);

        return $mutator;
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
