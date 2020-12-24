<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\CrossSellingStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal (flag:FEATURE_NEXT_10078)
 */
class CrossSellingCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @var AbstractProductCrossSellingRoute
     */
    private $crossSellingLoader;

    public function __construct(
        AbstractProductCrossSellingRoute $crossSellingLoader
    ) {
        $this->crossSellingLoader = $crossSellingLoader;
    }

    public function getType(): string
    {
        return 'cross-selling';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $productConfig = $config->get('product');

        if (!$productConfig) {
            return null;
        }

        if (!$productConfig->getValue()) {
            $request = $resolverContext->getRequest();
            if ($request->get('_route') !== 'frontend.detail.page') {
                return null;
            }

            $productId = $request->get('productId');
            if (!$productId) {
                return null;
            }

            $productConfig->assign([
                'value' => $productId,
            ]);
        }

        $criteria = new Criteria([$productConfig->getValue()]);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('cross-selling_' . $slot->getUniqueIdentifier(), ProductDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $context = $resolverContext->getSalesChannelContext();
        $struct = new CrossSellingStruct();
        $slot->setData($struct);

        $productConfig = $config->get('product');

        if (!$productConfig || $productConfig->getValue() === null) {
            return;
        }

        $crossSellings = $this->crossSellingLoader->load($productConfig->getValue(), $context)->getResult();

        if ($crossSellings->count()) {
            $struct->setCrossSellings($crossSellings);
        }
    }
}
