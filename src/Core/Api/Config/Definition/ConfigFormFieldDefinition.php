<?php declare(strict_types=1);

namespace Shopware\Api\Config\Definition;

use Shopware\Api\Config\Collection\ConfigFormFieldBasicCollection;
use Shopware\Api\Config\Collection\ConfigFormFieldDetailCollection;
use Shopware\Api\Config\Event\ConfigFormField\ConfigFormFieldDeletedEvent;
use Shopware\Api\Config\Event\ConfigFormField\ConfigFormFieldWrittenEvent;
use Shopware\Api\Config\Repository\ConfigFormFieldRepository;
use Shopware\Api\Config\Struct\ConfigFormFieldBasicStruct;
use Shopware\Api\Config\Struct\ConfigFormFieldDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;

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
