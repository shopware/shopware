<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Storefront\Framework\Page\PageletStruct;

class CustomerOrderPageletStruct extends PageletStruct
{
    /**
     * @var EntitySearchResult
     */
    private $orders;

    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var int
     */
    private $currentPage;

    /**
     * @var int
     */
    private $pageCount;

    public function __construct(
        EntitySearchResult $orders = null,
        Criteria $criteria = null,
        int $currentPage = 1,
        int $pageCount = 1
    ) {
        $this->orders = $orders;
        $this->criteria = $criteria;
        $this->currentPage = $currentPage;
        $this->pageCount = $pageCount;
    }

    public function getOrders(): EntitySearchResult
    {
        return $this->orders;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getPageCount(): int
    {
        return $this->pageCount;
    }
}
