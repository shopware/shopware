<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Encoder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentPageEncoder::class)]
class ContentPageEncoderTest extends TestCase
{
    #[TestDox('names the page keys id, name and version rather than the layout-prefixed struct property names')]
    public function testEncodeWritesThePageTripleUnderItsWireNames(): void
    {
        $body = $this->encode([new RenderedElement('r1', 'section')]);

        static::assertSame(['id', 'name', 'version', 'elements'], array_keys($body));
        static::assertSame('layout-1', $body['id']);
        static::assertSame('Landing', $body['name']);
        static::assertSame('1.0.0', $body['version']);
    }

    #[TestDox('carries the api alias on a nested element, not only on a root')]
    public function testEncodeWritesTheElementAliasAtEveryDepth(): void
    {
        $child = new RenderedElement('child', 'Sw:Content:Text');
        $body = $this->encode([new RenderedElement('root', 'Sw:Grid:Container', [], ['content' => [$child]])]);

        $root = $body['elements'][0];
        static::assertSame('content_element', $root['apiAlias']);
        static::assertSame('content_element', $root['slots']['content'][0]['apiAlias']);
    }

    #[TestDox('encodes a slot as a list of child elements keyed by the slot name')]
    public function testEncodeWritesSlotsAsListsOfChildren(): void
    {
        $first = new RenderedElement('first', 'Sw:Content:Text');
        $second = new RenderedElement('second', 'Sw:Content:Text');
        $body = $this->encode([new RenderedElement('root', 'Sw:Grid:Container', [], ['content' => [$first, $second]])]);

        $slots = $body['elements'][0]['slots'];
        static::assertSame(['content'], array_keys($slots));
        static::assertSame([0, 1], array_keys($slots['content']));
        static::assertSame(['first', 'second'], array_column($slots['content'], 'id'));
    }

    #[TestDox('omits slots and style on an element that has neither, and keeps an empty property map')]
    public function testEncodeOmitsEmptySlotsAndStyleButKeepsProperties(): void
    {
        $body = $this->encode([new RenderedElement('leaf', 'Sw:Content:Text')]);

        static::assertSame(['id', 'component', 'properties', 'apiAlias'], array_keys($body['elements'][0]));
        static::assertSame([], $body['elements'][0]['properties']);
    }

    #[TestDox('emits the style value map of an element that carries style')]
    public function testEncodeWritesTheStyleValueMap(): void
    {
        $element = new RenderedElement('root', 'Sw:Grid:Container', [], [], new ElementStyle(['col-span' => ['xs' => 6]]));

        $body = $this->encode([$element]);

        static::assertSame(['col-span' => ['xs' => 6]], $body['elements'][0]['style']);
    }

    #[TestDox('hands every struct-valued property leaf to the framework encoder, which is what applies the protection gate')]
    public function testEncodeDelegatesStructPropertyLeavesToTheStructEncoder(): void
    {
        $payload = new StubStruct();

        $structEncoder = static::createMock(StructEncoder::class);
        $structEncoder->expects($this->atLeastOnce())
            ->method('encode')
            ->with(
                static::identicalTo($payload),
                // Allow-all: the response listener has already removed includes/excludes, so nothing here may
                // re-introduce field filtering on an entity payload.
                static::callback(static function (ResponseFields $fields): bool {
                    static::assertTrue($fields->isAllowed('media', 'fileName'));
                    static::assertFalse($fields->hasNested('media', 'thumbnails'));

                    return true;
                }),
            )
            ->willReturn(['protection' => 'applied']);

        $element = new RenderedElement('leaf', 'Sw:Media:Image', ['media' => $payload]);
        $body = $this->encode([$element], $structEncoder);

        static::assertSame(['protection' => 'applied'], $body['elements'][0]['properties']['media']);
    }

    #[TestDox('reaches a struct nested inside an array property value')]
    public function testEncodeDescendsIntoArrayPropertyValues(): void
    {
        $payload = new StubStruct();

        $structEncoder = static::createStub(StructEncoder::class);
        $structEncoder
            ->method('encode')
            ->willReturn(['encoded' => true]);

        $element = new RenderedElement('leaf', 'Sw:Media:Image', ['gallery' => ['first' => $payload]]);
        $body = $this->encode([$element], $structEncoder);

        static::assertSame(['first' => ['encoded' => true]], $body['elements'][0]['properties']['gallery']);
    }

    #[TestDox('passes a scalar property through untouched')]
    public function testEncodeKeepsScalarPropertyValues(): void
    {
        $body = $this->encode([new RenderedElement('leaf', 'Sw:Content:Text', ['text' => 'Alpha copy', 'gap' => 24])]);

        static::assertSame(['text' => 'Alpha copy', 'gap' => 24], $body['elements'][0]['properties']);
    }

    #[TestDox('reports the page alias the carrier publishes')]
    public function testEncodeReturnsACarrierUnderThePageAlias(): void
    {
        $encoder = new ContentPageEncoder(static::createStub(StructEncoder::class));

        $carrier = $encoder->encode($this->renderResult([new RenderedElement('r1', 'section')]));

        static::assertSame('content_page', $carrier->getApiAlias());
    }

    /**
     * @param list<RenderedElement> $tree
     *
     * @return array<string, mixed>
     */
    private function encode(array $tree, ?StructEncoder $structEncoder = null): array
    {
        $encoder = new ContentPageEncoder($structEncoder ?? static::createStub(StructEncoder::class));

        return $encoder->encode($this->renderResult($tree))->jsonSerialize();
    }

    /**
     * @param list<RenderedElement> $tree
     */
    private function renderResult(array $tree): RenderResult
    {
        return new RenderResult(
            $tree,
            LayoutReference::create('layout-1', 'Landing', '1.0.0'),
            null,
        );
    }
}
