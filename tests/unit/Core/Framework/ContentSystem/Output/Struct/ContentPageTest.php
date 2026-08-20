<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentPage::class)]
class ContentPageTest extends TestCase
{
    #[TestDox('creates decomposed page with skeleton structure and assignment map')]
    public function testGetContentDecomposedPage(): void
    {
        [$page, $configProvider] = $this->createPageWithConfigProvider();

        $decomposed = $page->getContentDecomposedPage($configProvider, new ConfigCanonicalizer());

        static::assertCount(1, $decomposed->skeletons);
        static::assertSame('root-1', $decomposed->skeletons[0]->id);
        static::assertArrayHasKey('root-1', $decomposed->assignments);
    }

    #[TestDox('creates data page with hydrated data and assignments but without skeleton')]
    public function testGetContentDataPage(): void
    {
        [$page, $configProvider] = $this->createPageWithConfigProvider();

        $dataPage = $page->getContentDataPage($configProvider, new ConfigCanonicalizer());

        static::assertSame('layout-1', $dataPage->layoutId);
        static::assertArrayHasKey('root-1', $dataPage->assignments);
        static::assertCount(1, $dataPage->data);
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
