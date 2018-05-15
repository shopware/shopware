<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Definition;

use Shopware\System\Configuration\Collection\ConfigurationGroupBasicCollection;
use Shopware\System\Configuration\Collection\ConfigurationGroupDetailCollection;
use Shopware\System\Configuration\Event\ConfigurationGroup\ConfigurationGroupDeletedEvent;
use Shopware\System\Configuration\Event\ConfigurationGroup\ConfigurationGroupWrittenEvent;
use Shopware\System\Configuration\Repository\ConfigurationGroupRepository;
use Shopware\System\Configuration\Struct\ConfigurationGroupBasicStruct;
use Shopware\System\Configuration\Struct\ConfigurationGroupDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;

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
            (new OneToManyAssociationField('options', ConfigurationGroupOptionDefinition::class, 'configuration_group_id', false, 'id'))->setFlags(new CascadeDelete()),
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
