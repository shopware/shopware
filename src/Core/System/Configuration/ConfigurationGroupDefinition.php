<?php declare(strict_types=1);

namespace Shopware\System\Configuration;

use Shopware\System\Configuration\Collection\ConfigurationGroupBasicCollection;
use Shopware\System\Configuration\Collection\ConfigurationGroupDetailCollection;
use Shopware\System\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\System\Configuration\Definition\ConfigurationGroupTranslationDefinition;
use Shopware\System\Configuration\Event\ConfigurationGroup\ConfigurationGroupDeletedEvent;
use Shopware\System\Configuration\Event\ConfigurationGroup\ConfigurationGroupWrittenEvent;
use Shopware\System\Configuration\Repository\ConfigurationGroupRepository;
use Shopware\System\Configuration\Struct\ConfigurationGroupBasicStruct;
use Shopware\System\Configuration\Struct\ConfigurationGroupDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;

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
