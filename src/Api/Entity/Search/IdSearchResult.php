<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\Struct;

class IdSearchResult extends Struct
{
    /**
     * @var string[]
     */
    protected $data;

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

    /**
     * @var array
     */
    protected $ids;

    public function __construct(int $total, array $data, Criteria $criteria, TranslationContext $context)
    {
        $this->total = $total;
        $this->ids = array_keys($data);
        $this->data = $data;
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getIds(): array
    {
        return $this->ids;
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

    public function getData(): array
    {
        return $this->data;
    }
}
