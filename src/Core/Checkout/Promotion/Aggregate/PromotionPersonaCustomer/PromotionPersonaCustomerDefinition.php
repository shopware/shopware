<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaCustomer;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class PromotionPersonaCustomerDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'promotion_persona_customer';

    /**
     * This class is used as m:n relation between promotions and customers.
     * It gives the option to assign what customers may use this
     * promotion based on a whitelist algorithm.
     */
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id'),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id'))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),
        ]);
    }
}
