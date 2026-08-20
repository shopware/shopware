<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Format\FullResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FullResponseFactory::class)]
class FullResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentRouteResponse wrapping the content page')]
    public function testCreateResponseReturnsContentRouteResponse(): void
    {
        $factory = new FullResponseFactory();
        $root = ContentElementBuilder::create('section', 'r1')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse(new RenderResult([], LayoutReference::create('layout-1', 'Test', null), null, $page));

        static::assertInstanceOf(ContentRouteResponse::class, $response);
        static::assertSame($page, $response->getContentPage());
    }

    #[TestDox('serves its property values inline and asks for no value index')]
    public function testCollectsNoValueIndex(): void
    {
        static::assertFalse((new FullResponseFactory())->collectsValueIndex());
    }
}
