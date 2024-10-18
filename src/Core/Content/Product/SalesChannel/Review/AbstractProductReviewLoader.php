<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
abstract class AbstractProductReviewLoader
{
    abstract public function getDecorated(): AbstractProductReviewLoader;

    abstract public function load(Request $request, SalesChannelContext $context, string $productId, ?string $parentId = null): ProductReviewResult;
}
