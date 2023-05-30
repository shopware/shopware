<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('core')]
class ElasticsearchIndexingMessage implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IndexingDto $data,
        private readonly ?IndexerOffset $offset,
        private readonly Context $context
    ) {
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
