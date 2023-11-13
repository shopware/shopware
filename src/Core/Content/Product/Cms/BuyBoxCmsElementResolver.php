<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class BuyBoxCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductConfiguratorLoader $configuratorLoader,
        private readonly EntityRepository $repository
    ) {
    }

    public function getType(): string
    {
        return 'buy-box';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $buyBox = new BuyBoxStruct();
        $slot->setData($buyBox);

        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null) {
            return;
        }

        $product = null;

        if ($productConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $product = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getStringValue());
        }

        if ($productConfig->isStatic()) {
            $product = $this->getSlotProduct($slot, $result, $productConfig->getStringValue());
        }

        /** @var SalesChannelProductEntity|null $product */
        if ($product !== null) {
            $buyBox->setProduct($product);
            $buyBox->setProductId($product->getId());
            $buyBox->setConfiguratorSettings($this->configuratorLoader->load($product, $resolverContext->getSalesChannelContext()));
            $buyBox->setTotalReviews($this->getReviewsCount($product, $resolverContext->getSalesChannelContext()));
        }
    }

    private function getReviewsCount(SalesChannelProductEntity $product, SalesChannelContext $context): int
    {
        $reviewCriteria = $this->createReviewCriteria($context, $product->getParentId() ?? $product->getId());

        $aggregation = $this->repository->aggregate($reviewCriteria, $context->getContext())->get('review-count');

        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    private function createReviewCriteria(SalesChannelContext $context, string $productId): Criteria
    {
        $reviewFilters = [];
        $criteria = new Criteria();

        $reviewFilters[] = new EqualsFilter('status', true);
        if ($context->getCustomer() !== null) {
            $reviewFilters[] = new EqualsFilter('customerId', $context->getCustomer()->getId());
        }

        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new MultiFilter(MultiFilter::CONNECTION_OR, $reviewFilters),
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('product.id', $productId),
                    new EqualsFilter('product.parentId', $productId),
                ]),
            ])
        );

        $criteria->addAggregation(new CountAggregation('review-count', 'id'));

        return $criteria;
    }
}
