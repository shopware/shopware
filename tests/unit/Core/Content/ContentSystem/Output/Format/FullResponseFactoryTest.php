<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Output\Format\FullResponseFactory;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FullResponseFactory::class)]
class FullResponseFactoryTest extends TestCase
{
    #[TestDox('returns FULL rendering mode')]
    public function testGetRenderingModeReturnsFull(): void
    {
        $factory = new FullResponseFactory();

        static::assertSame(RenderingMode::FULL, $factory->getRenderingMode());
    }

    #[TestDox('creates ContentRouteResponse wrapping the content page')]
    public function testCreateResponseReturnsContentRouteResponse(): void
    {
        $factory = new FullResponseFactory();
        $root = ContentElementBuilder::create('section', 'r1')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse($page);

        static::assertInstanceOf(ContentRouteResponse::class, $response);
        static::assertSame($page, $response->getContentPage());
    }
}
