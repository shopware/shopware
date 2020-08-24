<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewSaveRoute;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.4.0 - Use `\Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute` instead
 */
class ProductReviewService
{
    /**
     * @var AbstractProductReviewSaveRoute
     */
    private $route;

    public function __construct(AbstractProductReviewSaveRoute $route)
    {
        $this->route = $route;
    }

    public function save(string $productId, DataBag $data, SalesChannelContext $context): void
    {
        $this->route->save($productId, $data->toRequestDataBag(), $context);
    }
}
