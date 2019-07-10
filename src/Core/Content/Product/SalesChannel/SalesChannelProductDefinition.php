<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
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
        if (!$criteria->hasAssociation('prices')) {
            $criteria->addAssociation('prices');
        }
        if (!$criteria->hasAssociation('unit')) {
            $criteria->addAssociation('unit');
        }
        if (!$criteria->hasAssociation('deliveryTime')) {
            $criteria->addAssociation('deliveryTime');
        }

        if (!$this->hasAvailableFilter($criteria)) {
            $criteria->addFilter(
                new ProductAvailableFilter($context->getSalesChannel()->getId())
            );
        }
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new JsonField('calculated_price', 'calculatedPrice'))->addFlags(new Runtime())
        );
        $fields->add(
            (new JsonField('calculated_listing_price', 'calculatedListingPrice'))->addFlags(new Runtime())
        );
        $fields->add(
            (new ListField('calculated_prices', 'calculatedPrices'))->addFlags(new Runtime())
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
