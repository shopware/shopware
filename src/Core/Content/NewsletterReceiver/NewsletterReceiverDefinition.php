<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;

class NewsletterReceiverDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'newsletter_receiver';
    }

    public static function isInheritanceAware(): bool
    {
        return true;
    }

    public static function getCollectionClass(): string
    {
        return NewsletterReceiverCollection::class;
    }

    public static function getEntityClass(): string
    {
        return NewsletterReceiverEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('email', 'email'))->addFlags(new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new StringField('first_name', 'firstName'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new StringField('last_name', 'lastName'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new StringField('zip_code', 'zipCode'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new StringField('city', 'city'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new StringField('street', 'street'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new StringField('state', 'state'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            new AttributesField(),

            new CreatedAtField(),
            new UpdatedAtField(),

            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, true, 'id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, true, 'id'))->addFlags(new Required(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, true, 'id'))->addFlags(new Required(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
        ]);
    }
}
