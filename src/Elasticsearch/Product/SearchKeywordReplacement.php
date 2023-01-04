<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

#[Package('core')]
class SearchKeywordReplacement extends SearchKeywordUpdater
{
    private SearchKeywordUpdater $decorated;

    private ElasticsearchHelper $helper;

    /**
     * @internal
     */
    public function __construct(SearchKeywordUpdater $decorated, ElasticsearchHelper $helper)
    {
        $this->decorated = $decorated;
        $this->helper = $helper;
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        if ($this->helper->allowIndexing()) {
            return;
        }

        $this->decorated->update($ids, $context);
    }

    public function reset(): void
    {
        $this->decorated->reset();
    }
}
