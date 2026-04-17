<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

/**
 * @deprecated tag:v6.8.0 - reason:remove-decorator - Will be removed, as `elasticsearch.indexing_enabled` already prevents the indexing of search keywords.
 */
#[Package('framework')]
class SearchKeywordReplacement extends SearchKeywordUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SearchKeywordUpdater $decorated,
        private readonly ElasticsearchHelper $helper
    ) {
    }

    /**
     * @param array<string> $ids
     *
     * @deprecated tag:v6.8.0 - reason:remove-decorator - Will be removed, use \Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater::update instead.
     */
    public function update(array $ids, Context $context): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            $this->decorated->update($ids, $context);

            return;
        }

        if ($this->helper->allowIndexing()) {
            return;
        }

        $this->decorated->update($ids, $context);
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-decorator - Will be removed, use \Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater::reset instead.
     */
    public function reset(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            $this->decorated->reset();

            return;
        }

        $this->decorated->reset();
    }
}
