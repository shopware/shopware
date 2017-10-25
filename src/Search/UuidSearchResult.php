<?php declare(strict_types=1);

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

    /**
     * @var Criteria
     */
    protected $criteria;

    public function __construct(int $total, array $uuids, Criteria $criteria)
    {
        $this->total = $total;
        $this->uuids = $uuids;
        $this->criteria = $criteria;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
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
