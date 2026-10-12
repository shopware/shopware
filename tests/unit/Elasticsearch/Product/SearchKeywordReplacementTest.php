<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\SearchKeywordReplacement;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SearchKeywordReplacement::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class SearchKeywordReplacementTest extends TestCase
{
    public function testSearchKeywordReplacement(): void
    {
        $decorated = $this->createMock(SearchKeywordUpdater::class);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowIndexing')->willReturn(true);

        $replacement = new SearchKeywordReplacement($decorated, $helper);
        $replacement->update([], Context::createDefaultContext());
        $decorated->expects($this->never())->method('update');
    }

    public function testSearchKeywordReplacementDisabled(): void
    {
        $decorated = $this->createMock(SearchKeywordUpdater::class);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowIndexing')->willReturn(false);

        $replacement = new SearchKeywordReplacement($decorated, $helper);
        $decorated->expects($this->once())->method('update');
        $replacement->update([], Context::createDefaultContext());
    }

    public function testReset(): void
    {
        $decorated = $this->createMock(SearchKeywordUpdater::class);
        $decorated->expects($this->once())->method('reset');
        $replacement = new SearchKeywordReplacement($decorated, static::createStub(ElasticsearchHelper::class));
        $replacement->reset();
    }
}
