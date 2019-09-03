<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Struct\Struct;

class IndexerRegistryPartialResult extends Struct
{
    /**
     * @var string|null
     */
    protected $indexer;

    /**
     * @var string|null
     */
    protected $lastId;

    public function __construct(?string $indexer, ?string $lastId)
    {
        $this->indexer = $indexer;
        $this->lastId = $lastId;
    }

    public function getIndexer(): ?string
    {
        return $this->indexer;
    }

    public function getLastId(): ?string
    {
        return $this->lastId;
    }
}
