<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentPage::class)]
class ContentPageTest extends TestCase
{
    public function testFromRenderResultMirrorsTheReferenceAndTree(): void
    {
        $tree = [new RenderedElement('root', 'Sw:Grid:Container')];
        $result = new RenderResult($tree, LayoutReference::create('layout-1', 'Landing', '1.0.0'), null);

        $page = ContentPage::fromRenderResult($result);

        static::assertSame('layout-1', $page->id);
        static::assertSame('Landing', $page->name);
        static::assertSame('1.0.0', $page->version);
        static::assertSame($tree, $page->elements);
        static::assertSame([], $page->settings);
    }

    public function testFromRenderResultExposesTheReferenceSettings(): void
    {
        $settings = ['scrollNavigation' => ['active' => true, 'position' => 'right']];
        $result = new RenderResult([], LayoutReference::create('layout-1', 'Landing', null, $settings), null);

        $page = ContentPage::fromRenderResult($result);

        static::assertNull($page->version);
        static::assertSame($settings, $page->settings);
    }
}
