<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver;

use Shopware\Core\Content\NewsletterReceiver\Aggregate\NewsletterReceiverTag\NewsletterReceiverTagDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Tag\TagDefinition;

class NewsletterReceiverDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'newsletter_receiver';
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
            (new StringField('email', 'email'))->addFlags(new Required()),
            new StringField('first_name', 'firstName'),
            new StringField('last_name', 'lastName'),
            new StringField('zip_code', 'zipCode'),
            new StringField('city', 'city'),
            new StringField('street', 'street'),
            new StringField('status', 'status'),
            new StringField('hash', 'hash'),
            new CustomFields(),

            new DateField('confirmed_at', 'confirmedAt'),

            new ManyToManyAssociationField('tags', TagDefinition::class, NewsletterReceiverTagDefinition::class, 'newsletter_receiver_id', 'tag_id'),

            new FkField('salutation_id', 'salutationId', SalutationDefinition::class),
            new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', true),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', true),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', true),
        ]);
    }
}
