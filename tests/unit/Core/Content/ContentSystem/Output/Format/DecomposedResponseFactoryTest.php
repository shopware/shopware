<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Output\Format\DecomposedResponseFactory;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(DecomposedResponseFactory::class)]
class DecomposedResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentDecomposedRouteResponse from content page')]
    public function testCreateResponseReturnsContentDecomposedRouteResponse(): void
    {
        $factory = new DecomposedResponseFactory(new DataLoaderConfigSerializerProvider(new ServiceLocator([])));
        $root = ContentElementBuilder::create('section', 'r1')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse($page);

        static::assertInstanceOf(ContentDecomposedRouteResponse::class, $response);
        $decomposedPage = $response->getContentDecomposedPage();
        static::assertSame('layout-1', $decomposedPage->layoutId);
        static::assertCount(1, $decomposedPage->skeletons);
        static::assertSame('r1', $decomposedPage->skeletons[0]->id);
    }
}
