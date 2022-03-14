<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductDescriptionReviewsCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly ProductReviewLoader $productReviewLoader)
    {
    }

    public function getType(): string
    {
        return 'product-description-reviews';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new ProductDescriptionReviewsStruct();
        $slot->setData($data);

        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null) {
            return;
        }

        $request = $resolverContext->getRequest();
        $ratingSuccess = (bool) $request->get('success', false);
        $data->setRatingSuccess($ratingSuccess);

        $product = null;

        if ($productConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $product = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getStringValue());
        }

        if ($productConfig->isStatic()) {
            $product = $this->getSlotProduct($slot, $result, $productConfig->getStringValue());
        }

        /** @var SalesChannelProductEntity|null $product */
        if ($product !== null) {
            $data->setProduct($product);
            $reviews = $this->loadProductReviews(
                $product,
                $request,
                $resolverContext->getSalesChannelContext()
            );
            $data->setReviews(ProductReviewResult::createFrom($reviews));
        }
    }

    private function loadProductReviews(SalesChannelProductEntity $product, Request $request, SalesChannelContext $context): ProductReviewLoaderResult
    {
        $reviewRequest = clone $request;
        $reviewRequest->attributes->set('productId', $product->getId());
        $reviewRequest->attributes->set('parentId', $product->getParentId());

        return $this->productReviewLoader->load($reviewRequest, $context);
    }
}
