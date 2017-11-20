<?php declare(strict_types=1);

namespace Shopware\Api\Search;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\Struct;

class UuidSearchResult extends Struct
{
    /**
     * @var string[]
     */
    protected $uuids;

    /**
     * @var int
     */
    protected $total;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(int $total, array $uuids, Criteria $criteria, TranslationContext $context)
    {
        $this->total = $total;
        $this->uuids = $uuids;
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getUuids(): array
    {
        return $this->uuids;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }
}
