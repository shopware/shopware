<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductPageCriteriaEvent
{
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(string $productId, Criteria $criteria, SalesChannelContext $context)
    {
        $this->productId = $productId;
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }
}
