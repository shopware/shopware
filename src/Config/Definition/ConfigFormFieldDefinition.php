<?php declare(strict_types=1);

namespace Shopware\Config\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Config\Collection\ConfigFormFieldBasicCollection;
use Shopware\Config\Collection\ConfigFormFieldDetailCollection;
use Shopware\Config\Event\ConfigFormField\ConfigFormFieldWrittenEvent;
use Shopware\Config\Repository\ConfigFormFieldRepository;
use Shopware\Config\Struct\ConfigFormFieldBasicStruct;
use Shopware\Config\Struct\ConfigFormFieldDetailStruct;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('config_form_uuid', 'configFormUuid', ConfigFormDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            new LongTextField('value', 'value'),
            new BoolField('required', 'required'),
            new IntField('position', 'position'),
            new IntField('scope', 'scope'),
            new LongTextField('options', 'options'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new TranslatedField(new StringField('label', 'label')),
            new TranslatedField(new LongTextField('description', 'description')),
            new ManyToOneAssociationField('configForm', 'config_form_uuid', ConfigFormDefinition::class, false),
            new TranslationsAssociationField('translations', ConfigFormFieldTranslationDefinition::class, 'config_form_field_uuid', false, 'uuid'),
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
