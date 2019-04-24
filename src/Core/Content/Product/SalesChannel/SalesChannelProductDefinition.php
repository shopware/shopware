<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelProductDefinition extends ProductDefinition implements SalesChannelDefinitionInterface
{
    use SalesChannelDefinitionTrait;

    public static function getEntityClass(): string
    {
        return SalesChannelProductEntity::class;
    }

    public static function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('product.active', true));

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new RangeFilter(
                        'product.visibilities.visibility', [RangeFilter::GTE => ProductVisibilityDefinition::VISIBILITY_LINK]
                    ),
                    new EqualsFilter(
                        'product.visibilities.salesChannelId', $context->getSalesChannel()->getId()
                    ),
                ]
            )
        );
    }

    protected static function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new BlobField('calculated_price', 'calculatedPrice'))->addFlags(new Deferred())
        );
        $fields->add(
            (new BlobField('calculated_listing_price', 'calculatedListingPrice'))->addFlags(new Deferred())
        );
        $fields->add(
            (new BlobField('calculated_price_rules', 'calculatedPriceRules'))->addFlags(new Deferred())
        );

        self::decorateDefinitions($fields);

        return $fields;
    }
}
