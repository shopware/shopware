<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class ProductElasticsearchKeywordIndexer implements IndexerInterface
{
    /**
     * @var IndexerInterface
     */
    private $decorated;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    public function __construct(
        IndexerInterface $decorated,
        ElasticsearchHelper $helper,
        ProductDefinition $productDefinition
    ) {
        $this->decorated = $decorated;
        $this->helper = $helper;
        $this->productDefinition = $productDefinition;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        // deactivate sql keyword indexing
        if ($this->helper->allowIndexing($this->productDefinition)) {
            $this->decorated->index($timestamp);
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        // deactivate sql keyword indexing
        if ($this->helper->allowIndexing($this->productDefinition)) {
            return;
        }

        $this->decorated->refresh($event);
    }
}
