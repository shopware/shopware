<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelProductDefinition extends ProductDefinition implements SalesChannelDefinitionInterface
{
    public function getEntityClass(): string
    {
        return SalesChannelProductEntity::class;
    }

    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria
            ->addAssociation('prices')
            ->addAssociation('unit')
            ->addAssociation('deliveryTime')
            ->addAssociation('cover.media')
        ;

        if (!$this->hasAvailableFilter($criteria)) {
            $criteria->addFilter(
                new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_LINK)
            );
        }
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new JsonField('calculated_price', 'calculatedPrice'))->addFlags(new ApiAware(), new Runtime())
        );

        $fields->add(
            (new ListField('calculated_prices', 'calculatedPrices'))->addFlags(new ApiAware(), new Runtime())
        );
        $fields->add(
            (new IntField('calculated_max_purchase', 'calculatedMaxPurchase'))->addFlags(new ApiAware(), new Runtime())
        );

        $fields->add(
            (new JsonField('calculated_cheapest_price', 'calculatedCheapestPrice'))->addFlags(new ApiAware(), new Runtime())
        );

        $fields->add(
            (new BoolField('is_new', 'isNew'))->addFlags(new ApiAware(), new Runtime())
        );
        $fields->add(
            (new OneToOneAssociationField('seoCategory', 'seoCategory', 'id', CategoryDefinition::class))->addFlags(new ApiAware(), new Runtime())
        );

        return $fields;
    }

    private function hasAvailableFilter(Criteria $criteria): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof ProductAvailableFilter) {
                return true;
            }
        }

        return false;
    }
}
