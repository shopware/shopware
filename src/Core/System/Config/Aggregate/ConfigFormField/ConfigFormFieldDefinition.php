<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormField;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\ConfigFormFieldTranslationDefinition;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\ConfigFormFieldValueDefinition;
use Shopware\Core\System\Config\ConfigFormDefinition;

class ConfigFormFieldDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'config_form_field';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new FkField('config_form_id', 'configFormId', ConfigFormDefinition::class),
            new ReferenceVersionField(ConfigFormDefinition::class),

            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('type', 'type'))->setFlags(new Required()),
            new LongTextField('value', 'value'),
            new BoolField('required', 'required'),
            new IntField('position', 'position'),
            new IntField('scope', 'scope'),
            new LongTextField('options', 'options'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslatedField(new StringField('label', 'label')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new TranslatedField(new LongTextField('description', 'description')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            new ManyToOneAssociationField('configForm', 'config_form_id', ConfigFormDefinition::class, false),
            (new TranslationsAssociationField('translations', ConfigFormFieldTranslationDefinition::class, 'config_form_field_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('values', ConfigFormFieldValueDefinition::class, 'config_form_field_id', false, 'id'))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ConfigFormFieldCollection::class;
    }

    public static function getStructClass(): string
    {
        return ConfigFormFieldStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ConfigFormFieldTranslationDefinition::class;
    }
}
