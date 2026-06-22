<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutAttachRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutDuplicateRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutInsertRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutMoveRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutRemoveRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutReplaceRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutUnwrapRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutWrapRequest;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Shopware\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ContentLayoutMutationController::class)]
class ContentLayoutMutationControllerTest extends TestCase
{
    #[TestDox('serializes the persisted mutation result into the layout, resolutions, diagnostics and affected ids')]
    public function testInsertSerializesMutationResult(): void
    {
        $result = new MutationResult([new ContentElement('el-1', 'Sw:Card')], ['el-1' => []], new DiagnosticsReport([]), ['el-1']);
        $controller = $this->controller($this->mutatorReturning($result));

        $response = $controller->insert('layout-1', new ContentLayoutInsertRequest('Sw:Card', null), Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->decode($response);
        static::assertSame('el-1', $body['layout'][0]['id']);
        static::assertSame(['el-1'], $body['affectedElementIds']);
        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertArrayHasKey('el-1', $body['resolutions']);
    }

    #[TestDox('encodes an empty resolutions map as a JSON object, not an array')]
    public function testEmptyResolutionsEncodeAsJsonObject(): void
    {
        $controller = $this->controller($this->mutatorReturning(new MutationResult([], [], new DiagnosticsReport([]), [])));

        $response = $controller->remove('layout-1', new ContentLayoutRemoveRequest('el', null), Context::createDefaultContext());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('"resolutions":{}', $content);
    }

    #[TestDox('reports dropped wiring keys in the persisted response')]
    public function testReplaceReportsDroppedWiring(): void
    {
        $result = new MutationResult([new ContentElement('el', 'Sw:New')], [], new DiagnosticsReport([]), ['el'], [], ['legacy']);
        $controller = $this->controller($this->mutatorReturning($result));

        $response = $controller->replace('layout-1', new ContentLayoutReplaceRequest('el', 'Sw:New', null), Context::createDefaultContext());

        static::assertSame(['legacy'], $this->decode($response)['droppedWiring']);
    }

    #[TestDox('passes the path layout id and expected version token through to the mutator')]
    public function testPassesLayoutIdAndExpectedVersionToMutator(): void
    {
        $capturedId = null;
        $capturedVersion = false;
        $mutator = $this->createMock(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturnCallback(
            function (string $layoutId, ?string $expectedVersion) use (&$capturedId, &$capturedVersion): MutationResult {
                $capturedId = $layoutId;
                $capturedVersion = $expectedVersion;

                return new MutationResult([], [], new DiagnosticsReport([]), []);
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
    #[TestDox('dispatches each route to the matching mutation op')]
    #[DataProvider('routeDispatch')]
    public function testRouteDispatchesExpectedOp(\Closure $invoke, string $expectedOp): void
    {
        $captured = null;
        $mutator = $this->createMock(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturnCallback(
            function (string $layoutId, ?string $expectedVersion, LayoutMutation $mutation) use (&$captured): MutationResult {
                $captured = $mutation;

                return new MutationResult([], [], new DiagnosticsReport([]), []);
            }
        );

        $invoke($this->controller($mutator));

        static::assertInstanceOf($expectedOp, $captured);
    }

    /**
     * @return iterable<string, array{\Closure(ContentLayoutMutationController): Response, class-string<LayoutMutation>}>
     */
    public static function routeDispatch(): iterable
    {
        $context = Context::createDefaultContext();

        yield 'insert' => [static fn (ContentLayoutMutationController $c): Response => $c->insert('l', new ContentLayoutInsertRequest('Sw:Card', null), $context), InsertElement::class];
        yield 'remove' => [static fn (ContentLayoutMutationController $c): Response => $c->remove('l', new ContentLayoutRemoveRequest('el', null), $context), RemoveElement::class];
        yield 'move' => [static fn (ContentLayoutMutationController $c): Response => $c->move('l', new ContentLayoutMoveRequest('el', null), $context), MoveElement::class];
        yield 'replace' => [static fn (ContentLayoutMutationController $c): Response => $c->replace('l', new ContentLayoutReplaceRequest('el', 'Sw:New', null), $context), ReplaceElement::class];
        yield 'duplicate' => [static fn (ContentLayoutMutationController $c): Response => $c->duplicate('l', new ContentLayoutDuplicateRequest('el', null), $context), DuplicateElement::class];
        yield 'wrap' => [static fn (ContentLayoutMutationController $c): Response => $c->wrap('l', new ContentLayoutWrapRequest(['a'], 'Sw:Container', null), $context), WrapElements::class];
        yield 'unwrap' => [static fn (ContentLayoutMutationController $c): Response => $c->unwrap('l', new ContentLayoutUnwrapRequest('el', null), $context), UnwrapElement::class];
        yield 'attach' => [static fn (ContentLayoutMutationController $c): Response => $c->attach('l', new ContentLayoutAttachRequest(['id' => 'incoming', 'component' => 'Sw:Card'], null), $context), AttachElement::class];
    }

    private function controller(PersistedLayoutMutator $mutator): ContentLayoutMutationController
    {
        return new ContentLayoutMutationController(
            $mutator,
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $this->elementSerializer(),
            $this->decoder(),
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

    private function mutatorReturning(MutationResult $result): PersistedLayoutMutator
    {
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturn($result);

        return $mutator;
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
