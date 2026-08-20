<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Encoder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDecomposedPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ResolvedValueIndexEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentDecomposedPageEncoder::class)]
class ContentDecomposedPageEncoderTest extends TestCase
{
    #[TestDox('names the body keys in their wire order, structure before the two index maps')]
    public function testEncodeWritesTheBodyKeysInTheirWireOrder(): void
    {
        $body = $this->encode(
            [new RenderedElement('root', 'Sw:Content:Text')],
            new ResolvedValueIndex(['r2' => 'Beta', 'r1' => 'Alpha'], ['root' => ['title' => 'r2']])
        );

        static::assertSame(['id', 'name', 'version', 'skeletons', 'data', 'assignments'], array_keys($body));
    }

    /**
     * The two maps carry different content, so an encoder that swapped them fails here rather than passing on
     * the key names alone.
     */
    #[TestDox('serves the value index data and assignment maps under their own keys')]
    public function testEncodeServesTheIndexMapsUnderTheirOwnKeys(): void
    {
        $data = ['r2' => 'Beta', 'r1' => 'Alpha'];
        $assignments = ['zulu-element' => ['title' => 'r2'], 'alpha-element' => ['title' => 'r1']];

        $body = $this->encode(
            [new RenderedElement('root', 'Sw:Content:Text')],
            new ResolvedValueIndex($data, $assignments)
        );

        static::assertSame($data, $body['data']);
        static::assertSame($assignments, $body['assignments']);
    }

    /**
     * The bridged page on the render result carries a different triple, so this fails if the encoder reads the
     * page instead of the reference.
     */
    #[TestDox('reads the page triple off the render result reference, not off the bridged page')]
    public function testEncodeReadsThePageTripleOffTheReference(): void
    {
        $result = new RenderResult(
            [],
            LayoutReference::create('layout-1', 'Landing', '1.0.0'),
            new ResolvedValueIndex([], []),
            new ContentPage('bridged-layout', [], 'Bridged', '9.9.9'),
        );

        $body = $this->encoder()->encode($result)->jsonSerialize();

        static::assertSame('layout-1', $body['id']);
        static::assertSame('Landing', $body['name']);
        static::assertSame('1.0.0', $body['version']);
    }

    /**
     * Style is passed before slots on purpose: the emitted node order must come from the encoder rather than
     * from the order the fixture happens to supply the two in.
     */
    #[TestDox('projects a skeleton node onto id, component, slots and style, dropping its property values')]
    public function testEncodeProjectsTheSkeletonWithoutPropertyValues(): void
    {
        $element = new RenderedElement(
            'root',
            'Sw:Grid:Container',
            ['headline' => 'Alpha copy'],
            style: new ElementStyle(['col-span' => ['xs' => 6]]),
            slots: ['content' => [new RenderedElement('child', 'Sw:Content:Text', ['text' => 'Beta copy'])]],
        );

        $node = $this->encode([$element])['skeletons'][0];

        static::assertSame(['id', 'component', 'slots', 'style', 'apiAlias'], array_keys($node));
        static::assertSame('root', $node['id']);
        static::assertSame('Sw:Grid:Container', $node['component']);
        static::assertSame(['col-span' => ['xs' => 6]], $node['style']);
        static::assertSame(['id', 'component', 'apiAlias'], array_keys($node['slots']['content'][0]));
    }

    #[TestDox('omits slots and style on a node that has neither')]
    public function testEncodeOmitsEmptySlotsAndStyle(): void
    {
        $node = $this->encode([new RenderedElement('leaf', 'Sw:Content:Text')])['skeletons'][0];

        static::assertSame(['id', 'component', 'apiAlias'], array_keys($node));
    }

    #[TestDox('carries the element alias last on every node at every depth, not only on a root')]
    public function testEncodeWritesTheElementAliasLastAtEveryDepth(): void
    {
        $grandchild = new RenderedElement('grandchild', 'Sw:Content:Text');
        $child = new RenderedElement('child', 'Sw:Grid:Container', [], ['media' => [$grandchild]]);
        $body = $this->encode([new RenderedElement('root', 'Sw:Grid:Container', [], ['content' => [$child]])]);

        $rootNode = $body['skeletons'][0];
        $childNode = $rootNode['slots']['content'][0];
        $grandchildNode = $childNode['slots']['media'][0];

        static::assertSame('apiAlias', array_key_last($rootNode));
        static::assertSame('apiAlias', array_key_last($childNode));
        static::assertSame('apiAlias', array_key_last($grandchildNode));
        static::assertSame('content_skeleton_element', $rootNode['apiAlias']);
        static::assertSame('content_skeleton_element', $childNode['apiAlias']);
        static::assertSame('content_skeleton_element', $grandchildNode['apiAlias']);
    }

    #[TestDox('reports the decomposed page alias the carrier publishes')]
    public function testEncodeReturnsACarrierUnderThePageAlias(): void
    {
        $carrier = $this->encoder()->encode($this->renderResult([], new ResolvedValueIndex([], [])));

        static::assertSame('content_decomposed_page', $carrier->getApiAlias());
    }

    /**
     * @param list<RenderedElement> $tree
     *
     * @return array<string, mixed>
     */
    private function encode(array $tree, ?ResolvedValueIndex $index = null): array
    {
        return $this->encoder()->encode($this->renderResult($tree, $index ?? new ResolvedValueIndex([], [])))->jsonSerialize();
    }

    private function encoder(): ContentDecomposedPageEncoder
    {
        return new ContentDecomposedPageEncoder(
            new ResolvedValueIndexEncoder(static::createStub(StructEncoder::class))
        );
    }

    /**
     * @param list<RenderedElement> $tree
     */
    private function renderResult(array $tree, ResolvedValueIndex $index): RenderResult
    {
        return new RenderResult(
            $tree,
            LayoutReference::create('layout-1', 'Landing', '1.0.0'),
            $index,
            new ContentPage('layout-1', [], 'Landing', '1.0.0'),
        );
    }
}
