<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateTypeTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'mail_template_type_translation';
    }

    public static function getEntityClass(): string
    {
        return MailTemplateTypeTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return MailTemplateTypeDefinition::class;
    }

    public static function getCollectionClass(): string
    {
        return MailTemplateTypeTranslationCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
            new CustomFields(),
        ]);
    }
}
