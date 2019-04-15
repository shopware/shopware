<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateTypeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'mail_template_type';
    }

    public static function getEntityClass(): string
    {
        return MailTemplateTypeEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return MailTemplateTypeCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),

            new TranslatedField('attributes'),

            (new TranslationsAssociationField(MailTemplateTypeTranslationDefinition::class, 'mail_template_type_id'))->addFlags(new Required()),
            new OneToManyAssociationField('mailTemplates', MailTemplateDefinition::class, 'mail_template_type_id'),
        ]);
    }
}
