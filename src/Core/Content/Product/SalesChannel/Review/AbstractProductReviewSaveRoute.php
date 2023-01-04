<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractProductReviewSaveRoute
{
    abstract public function getDecorated(): AbstractProductReviewSaveRoute;

    abstract public function save(string $productId, RequestDataBag $data, SalesChannelContext $context): NoContentResponse;
}
