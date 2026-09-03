<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Encoder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ContentDataPageEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ResolvedValueIndexEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
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
     * `ContentDataPageEncoder::encode()` is a single straight-line call with no branch of its own; the four
     * facts below are everything it produces from one render result, so one test carrying all of them proves
     * the same single code path without four redundant calls. The tree carries a two-level forest and the
     * index carries two entries under keys that would sort differently than the assignment map's, so a wire
     * order, key, or map mix-up fails a specific assertion rather than passing on incidental agreement.
     */
    #[TestDox('serves the body keys in wire order, the page triple, the index maps, and the api alias, with no structure key')]
    public function testEncodeServesTheDataPageBody(): void
    {
        $child = new RenderedElement('child', 'Sw:Content:Text');
        $tree = [new RenderedElement('root', 'Sw:Grid:Container', [], ['content' => [$child]])];
        $data = ['r2' => 'Beta', 'r1' => 'Alpha'];
        $assignments = ['zulu-element' => ['title' => 'r2'], 'alpha-element' => ['title' => 'r1']];

        $carrier = $this->encoder()->encode($this->renderResult($tree, new ResolvedValueIndex($data, $assignments)));
        $body = $carrier->jsonSerialize();

        static::assertSame(['id', 'name', 'version', 'data', 'assignments'], array_keys($body));
        static::assertSame('layout-1', $body['id']);
        static::assertSame('Landing', $body['name']);
        static::assertSame('1.0.0', $body['version']);
        static::assertSame($data, $body['data']);
        static::assertSame($assignments, $body['assignments']);
        static::assertArrayNotHasKey('skeletons', $body);
        static::assertArrayNotHasKey('elements', $body);
        static::assertSame('content_data_page', $carrier->getApiAlias());
    }

    #[TestDox('throws resolvedValueIndexMissing when the render result carries no value index')]
    public function testEncodeThrowsWhenIndexIsMissing(): void
    {
        $result = $this->renderResult([], null);

        $this->expectExceptionObject(ContentSystemException::resolvedValueIndexMissing('layout-1'));

        $this->encoder()->encode($result);
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
    private function renderResult(array $tree, ?ResolvedValueIndex $index): RenderResult
    {
        return new RenderResult(
            $tree,
            LayoutReference::create('layout-1', 'Landing', '1.0.0'),
            $index,
        );
    }
}
