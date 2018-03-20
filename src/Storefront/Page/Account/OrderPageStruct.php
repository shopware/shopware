<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Order\Struct\OrderSearchResult;
use Shopware\Framework\Struct\Struct;

class OrderPageStruct extends Struct
{
    /**
     * @var OrderSearchResult
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
        OrderSearchResult $orders,
        Criteria $criteria,
        int $currentPage = 1,
        int $pageCount = 1
    ) {
        $this->orders = $orders;
        $this->criteria = $criteria;
        $this->currentPage = $currentPage;
        $this->pageCount = $pageCount;
    }

    public function getOrders(): OrderSearchResult
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
