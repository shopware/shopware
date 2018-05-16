<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormField;

use Shopware\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldBasicCollection;
use Shopware\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldDetailCollection;
use Shopware\System\Config\ConfigFormDefinition;
use Shopware\System\Config\Aggregate\ConfigFormField\Event\ConfigFormFieldDeletedEvent;
use Shopware\System\Config\Aggregate\ConfigFormField\Event\ConfigFormFieldWrittenEvent;

use Shopware\System\Config\Aggregate\ConfigFormField\Struct\ConfigFormFieldBasicStruct;
use Shopware\System\Config\Aggregate\ConfigFormField\Struct\ConfigFormFieldDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\ConfigFormFieldTranslationDefinition;
use Shopware\System\Config\Aggregate\ConfigFormFieldValue\ConfigFormFieldValueDefinition;

class ConfigFormFieldDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'config_form_field';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
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

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigFormFieldRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigFormFieldBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ConfigFormFieldDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigFormFieldWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigFormFieldBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ConfigFormFieldTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ConfigFormFieldDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ConfigFormFieldDetailCollection::class;
    }
}
