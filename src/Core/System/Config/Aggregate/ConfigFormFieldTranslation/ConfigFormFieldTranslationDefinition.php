<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;

use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\ConfigFormFieldDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;

class ConfigFormFieldTranslationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'config_form_field_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('config_form_field_id', 'configFormFieldId', ConfigFormFieldDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ConfigFormFieldDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(LocaleDefinition::class))->setFlags(new Required()),

            new StringField('label', 'label'),
            new LongTextField('description', 'description'),
            new ManyToOneAssociationField('configFormField', 'config_form_field_id', ConfigFormFieldDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ConfigFormFieldTranslationCollection::class;
    }

    public static function getStructClass(): string
    {
        return ConfigFormFieldTranslationStruct::class;
    }
}
