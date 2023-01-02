<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Context;

/**
 * @package core
 */
#[Package('core')]
class ElasticsearchIndexingMessage
{
    private IndexingDto $data;

    private ?IndexerOffset $offset;

    private Context $context;

    /**
     * @internal
     */
    public function __construct(IndexingDto $data, ?IndexerOffset $offset, Context $context)
    {
        $this->data = $data;
        $this->offset = $offset;
        $this->context = $context;
    }

    public function getData(): IndexingDto
    {
        return $this->data;
    }

    public function getOffset(): ?IndexerOffset
    {
        return $this->offset;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
