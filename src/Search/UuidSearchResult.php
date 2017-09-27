<?php

namespace Shopware\Search;

use Shopware\Framework\Struct\Struct;

class UuidSearchResult extends Struct
{
    /**
     * @var int
     */
    protected $total;

    /**
     * @var string[]
     */
    protected $uuids;

    public function __construct(int $total, array $uuids)
    {
        $this->total = $total;
        $this->uuids = $uuids;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getUuids(): array
    {
        return $this->uuids;
    }
}
