<?php declare(strict_types=1);

namespace Shopware\Api\Search;

use Shopware\Context\Struct\TranslationContext;

trait SearchResultTrait
{
    /**
     * @var int
     */
    protected $total = 0;

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var Criteria
     */
    protected $criteria;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function setContext(TranslationContext $context): void
    {
        $this->context = $context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function setCriteria(Criteria $criteria): void
    {
        $this->criteria = $criteria;
    }
}
