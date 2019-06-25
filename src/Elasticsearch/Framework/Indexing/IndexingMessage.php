<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Context;

class IndexingMessage
{
    /**
     * @var string[]
     */
    protected $ids;

    /**
     * @var string
     */
    protected $index;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $entityName;

    public function __construct(array $ids, string $index, Context $context, string $entityName)
    {
        $this->ids = $ids;
        $this->index = $index;
        $this->context = $context;
        $this->entityName = $entityName;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }
}
