<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\ConfigurationGroupTranslationDefinition;
use Shopware\Core\Content\Configuration\Collection\ConfigurationGroupBasicCollection;
use Shopware\Core\Content\Configuration\Collection\ConfigurationGroupDetailCollection;
use Shopware\Core\Content\Configuration\Event\ConfigurationGroupDeletedEvent;
use Shopware\Core\Content\Configuration\Event\ConfigurationGroupWrittenEvent;
use Shopware\Core\Content\Configuration\Struct\ConfigurationGroupBasicStruct;
use Shopware\Core\Content\Configuration\Struct\ConfigurationGroupDetailStruct;

class ConfigurationGroupDefinition extends EntityDefinition
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
        return 'configuration_group';
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
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new IntField('position', 'position'),
            new BoolField('filterable', 'filterable'),
            new BoolField('comparable', 'comparable'),
            (new OneToManyAssociationField('options', Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition::class, 'configuration_group_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', ConfigurationGroupTranslationDefinition::class, 'configuration_group_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ConfigurationGroupRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ConfigurationGroupBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ConfigurationGroupDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ConfigurationGroupWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigurationGroupBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ConfigurationGroupTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ConfigurationGroupDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ConfigurationGroupDetailCollection::class;
    }
}
