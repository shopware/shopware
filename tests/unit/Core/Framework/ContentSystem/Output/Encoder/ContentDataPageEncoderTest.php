<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Encoder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDataPageEncoder;
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
#[CoversClass(ContentDataPageEncoder::class)]
class ContentDataPageEncoderTest extends TestCase
{
    /**
     * The bridged page on the render result carries a different triple, so this fails if the encoder reads the
     * page instead of the reference.
     */
    #[TestDox('names the body keys in their wire order and reads the page triple off the render result reference')]
    public function testEncodeWritesTheBodyKeysInTheirWireOrder(): void
    {
        $result = new RenderResult(
            [new RenderedElement('root', 'Sw:Content:Text')],
            LayoutReference::create('layout-1', 'Landing', '1.0.0'),
            new ResolvedValueIndex(['r2' => 'Beta', 'r1' => 'Alpha'], ['root' => ['title' => 'r2']]),
            new ContentPage('bridged-layout', [], 'Bridged', '9.9.9'),
        );

        $body = $this->encoder()->encode($result)->jsonSerialize();

        static::assertSame(['id', 'name', 'version', 'data', 'assignments'], array_keys($body));
        static::assertSame('layout-1', $body['id']);
        static::assertSame('Landing', $body['name']);
        static::assertSame('1.0.0', $body['version']);
    }

    /**
     * The render result carries a two-level forest, so the absence of a structure key is the encoder's decision
     * rather than an artefact of there being no structure to emit.
     */
    #[TestDox('emits no structure key at all, even for a render result carrying a forest')]
    public function testEncodeEmitsNoSkeletonKey(): void
    {
        $child = new RenderedElement('child', 'Sw:Content:Text');
        $tree = [new RenderedElement('root', 'Sw:Grid:Container', [], ['content' => [$child]])];

        $body = $this->encode($tree, new ResolvedValueIndex([], []));

        static::assertArrayNotHasKey('skeletons', $body);
        static::assertArrayNotHasKey('elements', $body);
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

        $body = $this->encode([], new ResolvedValueIndex($data, $assignments));

        static::assertSame($data, $body['data']);
        static::assertSame($assignments, $body['assignments']);
    }

    #[TestDox('reports the data page alias the carrier publishes')]
    public function testEncodeReturnsACarrierUnderThePageAlias(): void
    {
        $carrier = $this->encoder()->encode($this->renderResult([], new ResolvedValueIndex([], [])));

        static::assertSame('content_data_page', $carrier->getApiAlias());
    }

    /**
     * @param list<RenderedElement> $tree
     *
     * @return array<string, mixed>
     */
    private function encode(array $tree, ResolvedValueIndex $index): array
    {
        return $this->encoder()->encode($this->renderResult($tree, $index))->jsonSerialize();
    }

    private function encoder(): ContentDataPageEncoder
    {
        return new ContentDataPageEncoder(
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
