<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\ConfigFormFieldDefinition;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\Collection\ConfigFormFieldValueBasicCollection;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\Struct\ConfigFormFieldValueBasicStruct;

class ConfigFormFieldValueDefinition extends EntityDefinition
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
        return 'config_form_field_value';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('config_form_field_id', 'configFormFieldId', ConfigFormFieldDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ConfigFormFieldDefinition::class))->setFlags(new Required()),

            (new LongTextField('value', 'value'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('configFormField', 'config_form_field_id', ConfigFormFieldDefinition::class, false),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        return ConfigFormFieldValueRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigFormFieldValueBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ConfigFormFieldValueDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigFormFieldValueWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigFormFieldValueBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ConfigFormFieldValueDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ConfigFormFieldValueDetailCollection::class;
    }
}
