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
     * @var array|null
     */
    protected $offset;

    public function __construct(?string $indexer, ?array $offset)
    {
        $this->indexer = $indexer;
        $this->offset = $offset;
    }

    public function getIndexer(): ?string
    {
        return $this->indexer;
    }

    public function getOffset(): ?array
    {
        return $this->offset;
    }

    public function getApiAlias(): string
    {
        return 'dal_indexer_registry_partial_result';
    }
}
