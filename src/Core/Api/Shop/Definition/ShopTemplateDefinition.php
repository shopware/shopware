<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Plugin\Definition\PluginDefinition;
use Shopware\Api\Shop\Collection\ShopTemplateBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplate\ShopTemplateDeletedEvent;
use Shopware\Api\Shop\Event\ShopTemplate\ShopTemplateWrittenEvent;
use Shopware\Api\Shop\Repository\ShopTemplateRepository;
use Shopware\Api\Shop\Struct\ShopTemplateBasicStruct;
use Shopware\Api\Shop\Struct\ShopTemplateDetailStruct;

class ShopTemplateDefinition extends EntityDefinition
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
        return 'shop_template';
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
            new FkField('plugin_id', 'pluginId', PluginDefinition::class),
            new FkField('parent_id', 'parentId', self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),
            (new StringField('template', 'template'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new BoolField('emotion', 'emotion'))->setFlags(new Required()),
            (new StringField('description', 'description'))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new StringField('author', 'author'))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            new StringField('license', 'license'),
            new BoolField('esi', 'esi'),
            new BoolField('style_support', 'styleSupport'),
            new IntField('version', 'version'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class, false),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, false),
            (new OneToManyAssociationField('shops', ShopDefinition::class, 'document_template_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('shops', ShopDefinition::class, 'shop_template_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('children', self::class, 'parent_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('configForms', ShopTemplateConfigFormDefinition::class, 'shop_template_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('configFormFields', ShopTemplateConfigFormFieldDefinition::class, 'shop_template_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('configPresets', ShopTemplateConfigPresetDefinition::class, 'shop_template_id', false, 'id'))->setFlags(new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShopTemplateRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShopTemplateBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ShopTemplateDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShopTemplateWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShopTemplateBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ShopTemplateDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShopTemplateDetailCollection::class;
    }
}
