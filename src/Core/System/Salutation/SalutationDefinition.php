<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Content\NewsletterReceiver\NewsletterReceiverDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationDefinition;
use Shopware\Core\System\Salutation\SalesChannel\SalesChannelSalutationDefinition;

class SalutationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'salutation';
    }

    public static function getSalesChannelDecorationDefinition(): string
    {
        return SalesChannelSalutationDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return SalutationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SalutationEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('salutation_key', 'salutationKey'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('displayName'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('letterName'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new CreatedAtField(),
            new UpdatedAtField(),

            (new TranslationsAssociationField(SalutationTranslationDefinition::class, 'salutation_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderCustomers', OrderCustomerDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('newsletterReceivers', NewsletterReceiverDefinition::class, 'salutation_id', 'id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
