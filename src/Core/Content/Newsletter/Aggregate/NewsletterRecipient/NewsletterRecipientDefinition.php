<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipientTag\NewsletterRecipientTagDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Tag\TagDefinition;

#[Package('after-sales')]
class NewsletterRecipientDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'newsletter_recipient';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NewsletterRecipientCollection::class;
    }

    public function getEntityClass(): string
    {
        return NewsletterRecipientEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of newsletter recipient.'),
            (new StringField('email', 'email'))->addFlags(new Required())->setDescription('Email of the recipient.'),
            (new StringField('title', 'title'))->setDescription('Title of the recipient\'s newsletter.'),
            (new StringField('first_name', 'firstName'))->setDescription('First name of the recipient.'),
            (new StringField('last_name', 'lastName'))->setDescription('Last name of the recipient.'),
            (new StringField('zip_code', 'zipCode'))->setDescription('Zipcode of the recipient\'s address.'),
            (new StringField('city', 'city'))->setDescription('City of the recipient.'),
            (new StringField('street', 'street'))->setDescription('Street of the recipient.'),
            (new StringField('status', 'status'))->addFlags(new Required())->setDescription('When status is set, the NewsletterRecipient is made visible.'),
            (new StringField('hash', 'hash'))->addFlags(new Required())->setDescription('Password hash for account recovery.'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new DateTimeField('confirmed_at', 'confirmedAt'))->setDescription('Date and time when the Newsletter was received.'),
            new ManyToManyAssociationField('tags', TagDefinition::class, NewsletterRecipientTagDefinition::class, 'newsletter_recipient_id', 'tag_id'),
            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->setDescription('Unique identity of salutation.'),
            new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', false),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required())->setDescription('Unique identity of language.'),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the sales channel.'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
