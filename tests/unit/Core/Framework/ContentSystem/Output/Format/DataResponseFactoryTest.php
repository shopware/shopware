<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Output\Format\DataResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDataRouteResponse;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(DataResponseFactory::class)]
class DataResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentDataRouteResponse from content page')]
    public function testCreateResponseReturnsContentDataRouteResponse(): void
    {
        $factory = new DataResponseFactory(new DataLoaderConfigSerializerProvider(new ServiceLocator([])));
        $root = ContentElementBuilder::create('section', 'r1')->withProperty('background', 'blue')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse($page);

        static::assertInstanceOf(ContentDataRouteResponse::class, $response);
        $dataPage = $response->getContentDataPage();
        static::assertSame('layout-1', $dataPage->layoutId);
        static::assertArrayHasKey('r1', $dataPage->assignments);
        static::assertCount(1, $dataPage->data);
    }
}
