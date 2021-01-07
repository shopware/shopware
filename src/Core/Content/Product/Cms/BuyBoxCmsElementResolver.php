<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal (flag:FEATURE_NEXT_10078)
 */
class BuyBoxCmsElementResolver extends AbstractCmsElementResolver
{
    private const PRODUCT_DETAIL_ROUTE = 'frontend.detail.page';

    /**
     * @var AbstractProductDetailRoute
     */
    private $productRoute;

    public function __construct(
        AbstractProductDetailRoute $productRoute
    ) {
        $this->productRoute = $productRoute;
    }

    public function getType(): string
    {
        return 'buy-box';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $productConfig = $config->get('product');

        if (!$productConfig || $productConfig->getValue() === null) {
            return null;
        }

        $criteria = new Criteria([$productConfig->getValue()]);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('product_' . $slot->getUniqueIdentifier(), ProductDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $buyBox = new BuyBoxStruct();
        $slot->setData($buyBox);

        $config = $slot->getFieldConfig();
        $productConfig = $config->get('product');

        if (!$productConfig) {
            return;
        }

        if ($resolverContext instanceof EntityResolverContext && $resolverContext->getRequest()->get('_route') === self::PRODUCT_DETAIL_ROUTE) {
            $productConfig->assign([
                'value' => $resolverContext->getRequest()->get('productId'),
            ]);
        }

        if (!$productConfig->getValue()) {
            return;
        }

        $this->resolveProductFromRemote($buyBox, $resolverContext, $productConfig->getValue());
    }

    private function resolveProductFromRemote(BuyBoxStruct $buyBox, ResolverContext $resolverContext, string $productId): void
    {
        $context = $resolverContext->getSalesChannelContext();
        $request = $resolverContext->getRequest();

        $result = $this->productRoute->load($productId, $request, $context, new Criteria());
        $product = $result->getProduct();

        /** @var PropertyGroupCollection $configurator */
        $configurator = $result->getConfigurator();

        // TODO: NEXT-12803 - Get totalReviews by ProductReviewLoader and store it in buyBox after NEXT-11745 completed

        $buyBox->setConfiguratorSettings($configurator);
        $buyBox->setProduct($product);
        $buyBox->setProductId($product->getId());
    }
}
