<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelCategoryDefinition extends CategoryDefinition implements SalesChannelDefinitionInterface
{
    use SalesChannelDefinitionTrait;

    public static function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('category.active', true));
    }

    protected static function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        self::decorateDefinitions($fields);

        return $fields;
    }
}
