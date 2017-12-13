<?php declare(strict_types=1);

namespace Shopware\Plugin\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Config\Definition\ConfigFormDefinition;
use Shopware\Payment\Definition\PaymentMethodDefinition;
use Shopware\Plugin\Collection\PluginBasicCollection;
use Shopware\Plugin\Collection\PluginDetailCollection;
use Shopware\Plugin\Event\Plugin\PluginWrittenEvent;
use Shopware\Plugin\Repository\PluginRepository;
use Shopware\Plugin\Struct\PluginBasicStruct;
use Shopware\Plugin\Struct\PluginDetailStruct;
use Shopware\Shop\Definition\ShopTemplateDefinition;

class PluginDefinition extends EntityDefinition
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
        return 'plugin';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('label', 'label'))->setFlags(new Required()),
            (new BoolField('active', 'active'))->setFlags(new Required()),
            (new StringField('version', 'version'))->setFlags(new Required()),
            (new BoolField('capability_update', 'capabilityUpdate'))->setFlags(new Required()),
            (new BoolField('capability_install', 'capabilityInstall'))->setFlags(new Required()),
            (new BoolField('capability_enable', 'capabilityEnable'))->setFlags(new Required()),
            (new BoolField('capability_secure_uninstall', 'capabilitySecureUninstall'))->setFlags(new Required()),
            new LongTextField('description', 'description'),
            new LongTextField('description_long', 'descriptionLong'),
            new DateField('created_at', 'createdAt'),
            new DateField('installation_date', 'installationDate'),
            new DateField('update_date', 'updateDate'),
            new DateField('refresh_date', 'refreshDate'),
            new StringField('author', 'author'),
            new StringField('copyright', 'copyright'),
            new StringField('license', 'license'),
            new StringField('support', 'support'),
            new LongTextField('changes', 'changes'),
            new StringField('link', 'link'),
            new StringField('store_version', 'storeVersion'),
            new DateField('store_date', 'storeDate'),
            new StringField('update_source', 'updateSource'),
            new StringField('update_version', 'updateVersion'),
            new DateField('updated_at', 'updatedAt'),
            new OneToManyAssociationField('configForms', ConfigFormDefinition::class, 'plugin_uuid', false, 'uuid'),
            new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'plugin_uuid', false, 'uuid'),
            new OneToManyAssociationField('shopTemplates', ShopTemplateDefinition::class, 'plugin_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return PluginRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return PluginBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return PluginWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return PluginBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return PluginDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return PluginDetailCollection::class;
    }
}
