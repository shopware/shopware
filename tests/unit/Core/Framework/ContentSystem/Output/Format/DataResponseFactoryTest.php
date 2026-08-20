<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Format\DataResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDataRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DataResponseFactory::class)]
class DataResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentDataRouteResponse from content page')]
    public function testCreateResponseReturnsContentDataRouteResponse(): void
    {
        $factory = new DataResponseFactory(new DataLoaderConfigSerializerProvider(new ServiceLocator([])), new ConfigCanonicalizer());
        $root = ContentElementBuilder::create('section', 'r1')->withProperty('background', 'blue')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse(new RenderResult([], LayoutReference::create('layout-1', 'Test', null), null, $page));

        static::assertInstanceOf(ContentDataRouteResponse::class, $response);
        $dataPage = $response->getContentDataPage();
        static::assertSame('layout-1', $dataPage->layoutId);
        static::assertArrayHasKey('r1', $dataPage->assignments);
        static::assertCount(1, $dataPage->data);
    }

    #[TestDox('rebuilds its body from the value index and asks for its collection')]
    public function testCollectsTheValueIndex(): void
    {
        $factory = new DataResponseFactory(new DataLoaderConfigSerializerProvider(new ServiceLocator([])), new ConfigCanonicalizer());

        static::assertTrue($factory->collectsValueIndex());
    }
}
