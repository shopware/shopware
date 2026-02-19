<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Output\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContentPage::class)]
class ContentPageTest extends TestCase
{
    #[TestDox('creates skeleton page with element structure but without hydrated property data')]
    public function testGetContentSkeletonPage(): void
    {
        $child = ContentElementBuilder::create('text', 'child-1')
            ->withProperty('title', 'Hello')
            ->build();

        $root = ContentElementBuilder::create('section', 'root-1')
            ->withSlot('default', [$child])
            ->build();

        $page = new ContentPage('layout-1', [$root], 'Test Layout', 'v1');

        $skeleton = $page->getContentSkeletonPage();

        static::assertSame('layout-1', $skeleton->layoutId);
        static::assertCount(1, $skeleton->elements);
        static::assertSame('root-1', $skeleton->elements[0]->id);
        static::assertSame('section', $skeleton->elements[0]->component);
    }

    #[TestDox('creates decomposed page with skeleton structure')]
    public function testGetContentDecomposedPageProducesSkeletonStructure(): void
    {
        $root = ContentElementBuilder::create('section', 'root-1')
            ->withProperty('title', 'Hello')
            ->build();

        $configProvider = new DataLoaderConfigSerializerProvider(new ServiceLocator([]));

        $page = new ContentPage('layout-1', [$root], 'Test Layout', 'v1');

        $decomposed = $page->getContentDecomposedPage($configProvider);

        static::assertCount(1, $decomposed->skeletons);
        static::assertSame('root-1', $decomposed->skeletons[0]->id);
    }

    #[TestDox('creates decomposed page with assignment map')]
    public function testGetContentDecomposedPageBuildsAssignmentMap(): void
    {
        $root = ContentElementBuilder::create('section', 'root-1')
            ->withProperty('title', 'Hello')
            ->build();

        $configProvider = new DataLoaderConfigSerializerProvider(new ServiceLocator([]));

        $page = new ContentPage('layout-1', [$root], 'Test Layout', 'v1');

        $decomposed = $page->getContentDecomposedPage($configProvider);

        static::assertArrayHasKey('root-1', $decomposed->assignments);
    }

    #[TestDox('creates data page with hydrated data and assignments but without skeleton')]
    public function testGetContentDataPage(): void
    {
        $root = ContentElementBuilder::create('section', 'root-1')
            ->withProperty('title', 'Hello')
            ->build();

        $configProvider = new DataLoaderConfigSerializerProvider(new ServiceLocator([]));

        $page = new ContentPage('layout-1', [$root], 'Test Layout', 'v1');

        $dataPage = $page->getContentDataPage($configProvider);

        static::assertSame('layout-1', $dataPage->layoutId);
        static::assertArrayHasKey('root-1', $dataPage->assignments);
        static::assertCount(1, $dataPage->data);
    }
}
