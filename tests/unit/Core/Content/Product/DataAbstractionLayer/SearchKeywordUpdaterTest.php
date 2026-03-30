<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @internal
 */
#[CoversClass(SearchKeywordUpdater::class)]
class SearchKeywordUpdaterTest extends TestCase
{
    public function testDisabledIndexingSkipsUpdate(): void
    {
        $languageRepository = $this->createMock(EntityRepository::class);
        $productRepository = $this->createMock(EntityRepository::class);
        $analyzer = $this->createMock(ProductSearchKeywordAnalyzerInterface::class);

        $languageRepository->expects($this->never())->method('search');
        $productRepository->expects($this->never())->method('search');
        $analyzer->expects($this->never())->method('analyze');

        $updater = new SearchKeywordUpdater(
            $this->createMock(Connection::class),
            $languageRepository,
            $productRepository,
            $analyzer,
            false
        );

        $updater->update(['f70db8f6eb884b1ea2a691da3f74dc93'], Context::createDefaultContext());
    }
}
