<?php declare(strict_types=1);

namespace Shopware\System\Config\Definition;

use Shopware\System\Config\Collection\ConfigFormBasicCollection;
use Shopware\System\Config\Collection\ConfigFormDetailCollection;
use Shopware\System\Config\Event\ConfigForm\ConfigFormDeletedEvent;
use Shopware\System\Config\Event\ConfigForm\ConfigFormWrittenEvent;
use Shopware\System\Config\Repository\ConfigFormRepository;
use Shopware\System\Config\Struct\ConfigFormBasicStruct;
use Shopware\System\Config\Struct\ConfigFormDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
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
use Shopware\Api\Plugin\Definition\PluginDefinition;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new FkField('parent_id', 'parentId', self::class),
            new ReferenceVersionField(self::class),

            new FkField('plugin_id', 'pluginId', PluginDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslatedField(new LongTextField('description', 'description')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new TranslatedField(new StringField('label', 'label')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, false),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, false),
            (new OneToManyAssociationField('children', self::class, 'parent_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('fields', ConfigFormFieldDefinition::class, 'config_form_id', false, 'id'))->setFlags(new CascadeDelete(), new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new TranslationsAssociationField('translations', ConfigFormTranslationDefinition::class, 'config_form_id', false, 'id'))->setFlags(new CascadeDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return ConfigFormDeletedEvent::class;
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
