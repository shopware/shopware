<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class ProductKeywordIndexer implements IndexerInterface
{
    /**
     * @var IndexerInterface
     */
    private $decorated;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    public function __construct(IndexerInterface $decorated, ElasticsearchHelper $helper)
    {
        $this->decorated = $decorated;
        $this->helper = $helper;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        // deactivate sql keyword indexing
        if ($this->helper->allowIndexing()) {
            return;
        }

        $this->decorated->index($timestamp);
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        // deactivate sql keyword indexing
        if ($this->helper->allowIndexing()) {
            return null;
        }

        return $this->decorated->partial($lastId, $timestamp);
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        // deactivate sql keyword indexing
        if ($this->helper->allowIndexing()) {
            return;
        }

        $this->decorated->refresh($event);
    }

    public static function getName(): string
    {
        return 'Swag.ProductKeywordIndexer';
    }
}
