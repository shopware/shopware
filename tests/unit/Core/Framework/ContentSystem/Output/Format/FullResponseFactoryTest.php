<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Format\FullResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FullResponseFactory::class)]
class FullResponseFactoryTest extends TestCase
{
    #[TestDox('creates a ContentRouteResponse whose page projects the render result it carries')]
    public function testCreateResponseReturnsContentRouteResponse(): void
    {
        $factory = new FullResponseFactory();
        $root = new RenderedElement('r1', 'section');

        $result = new RenderResult([$root], LayoutReference::create('layout-1', 'Test', null), null);

        $response = $factory->createResponse($result);

        static::assertInstanceOf(ContentRouteResponse::class, $response);
        static::assertSame($result, $response->getRenderResult());

        $page = $response->getContentPage();
        static::assertSame('layout-1', $page->id);
        static::assertSame('Test', $page->name);
        static::assertNull($page->version);
        static::assertSame([$root], $page->elements);
    }

    #[TestDox('serves its property values inline and asks for no value index')]
    public function testCollectsNoValueIndex(): void
    {
        static::assertFalse((new FullResponseFactory())->collectsValueIndex());
    }
}
