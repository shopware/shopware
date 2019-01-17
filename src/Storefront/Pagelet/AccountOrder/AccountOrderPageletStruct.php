<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountOrder;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;

class AccountOrderPageletStruct extends Struct
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

    /**
     * @param EntitySearchResult $orders
     */
    public function setOrders(EntitySearchResult $orders): void
    {
        $this->orders = $orders;
    }

    /**
     * @param Criteria $criteria
     */
    public function setCriteria(Criteria $criteria): void
    {
        $this->criteria = $criteria;
    }

    /**
     * @param int $currentPage
     */
    public function setCurrentPage(int $currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    /**
     * @param int $pageCount
     */
    public function setPageCount(int $pageCount): void
    {
        $this->pageCount = $pageCount;
    }
}
