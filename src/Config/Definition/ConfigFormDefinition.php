<?php declare(strict_types=1);

namespace Shopware\Config\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Config\Collection\ConfigFormBasicCollection;
use Shopware\Config\Collection\ConfigFormDetailCollection;
use Shopware\Config\Event\ConfigForm\ConfigFormWrittenEvent;
use Shopware\Config\Repository\ConfigFormRepository;
use Shopware\Config\Struct\ConfigFormBasicStruct;
use Shopware\Config\Struct\ConfigFormDetailStruct;
use Shopware\Plugin\Definition\PluginDefinition;

class ConfigFormDefinition extends EntityDefinition
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
        return 'config_form';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('parent_uuid', 'parentUuid', self::class),
            new FkField('plugin_uuid', 'pluginUuid', PluginDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new TranslatedField(new StringField('label', 'label')),
            new TranslatedField(new LongTextField('description', 'description')),
            new ManyToOneAssociationField('parent', 'parent_uuid', self::class, false),
            new ManyToOneAssociationField('plugin', 'plugin_uuid', PluginDefinition::class, false),
            new OneToManyAssociationField('fields', ConfigFormFieldDefinition::class, 'config_form_uuid', false, 'uuid'),
            new TranslationsAssociationField('translations', ConfigFormTranslationDefinition::class, 'config_form_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigFormRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigFormBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigFormWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigFormBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ConfigFormTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ConfigFormDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ConfigFormDetailCollection::class;
    }
}
