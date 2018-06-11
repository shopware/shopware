<?php declare(strict_types=1);

namespace Shopware\Core\System\Config;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\ConfigFormFieldDefinition;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\ConfigFormTranslationDefinition;
use Shopware\Core\System\Config\Collection\ConfigFormBasicCollection;
use Shopware\Core\System\Config\Struct\ConfigFormBasicStruct;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
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
    }


    public static function getBasicCollectionClass(): string
    {
        return ConfigFormBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return ConfigFormBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ConfigFormTranslationDefinition::class;
    }
}
