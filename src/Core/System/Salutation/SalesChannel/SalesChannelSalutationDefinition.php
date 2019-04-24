<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationDefinition;

class SalesChannelSalutationDefinition extends SalutationDefinition implements SalesChannelDefinitionInterface
{
    use SalesChannelDefinitionTrait;

    public static function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
    }

    protected static function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        self::decorateDefinitions($fields);

        return $fields;
    }
}
