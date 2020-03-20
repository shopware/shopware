<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class SearchKeywordReplacement extends SearchKeywordUpdater
{
    /**
     * @var SearchKeywordUpdater
     */
    private $decorated;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    public function __construct(SearchKeywordUpdater $decorated, ElasticsearchHelper $helper)
    {
        $this->decorated = $decorated;
        $this->helper = $helper;
    }

    public function update(array $ids, Context $context): void
    {
        if ($this->helper->allowIndexing()) {
            return;
        }

        $this->decorated->update($ids, $context);
    }
}
