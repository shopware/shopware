<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Output\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(ContentPage::class)]
class ContentPageTest extends TestCase
{
    #[TestDox('creates skeleton page preserving layout id, element structure, and layout version')]
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
        static::assertSame('v1', $skeleton->layoutVersion);
    }

    #[TestDox('creates decomposed page with skeleton structure and assignment map')]
    public function testGetContentDecomposedPage(): void
    {
        [$page, $configProvider] = $this->createPageWithConfigProvider();

        $decomposed = $page->getContentDecomposedPage($configProvider);

        static::assertCount(1, $decomposed->skeletons);
        static::assertSame('root-1', $decomposed->skeletons[0]->id);
        static::assertArrayHasKey('root-1', $decomposed->assignments);
    }

    #[TestDox('creates data page with hydrated data and assignments but without skeleton')]
    public function testGetContentDataPage(): void
    {
        [$page, $configProvider] = $this->createPageWithConfigProvider();

        $dataPage = $page->getContentDataPage($configProvider);

        static::assertSame('layout-1', $dataPage->layoutId);
        static::assertArrayHasKey('root-1', $dataPage->assignments);
        static::assertCount(1, $dataPage->data);
    }

    #[TestDox('creates skeleton page with empty elements and null layout version')]
    public function testGetContentSkeletonPageWithEmptyElements(): void
    {
        $page = new ContentPage('layout-1', [], 'Test Layout', null);

        $skeleton = $page->getContentSkeletonPage();

        static::assertSame('layout-1', $skeleton->layoutId);
        static::assertCount(0, $skeleton->elements);
        static::assertNull($skeleton->layoutVersion);
    }

    /**
     * @return array{ContentPage, DataLoaderConfigSerializerProvider}
     */
    private function createPageWithConfigProvider(): array
    {
        $root = ContentElementBuilder::create('section', 'root-1')
            ->withProperty('title', 'Hello')
            ->build();

        $configProvider = new DataLoaderConfigSerializerProvider(new ServiceLocator([]));
        $page = new ContentPage('layout-1', [$root], 'Test Layout', 'v1');

        return [$page, $configProvider];
    }
}
