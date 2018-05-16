<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Definition;

use Shopware\System\Config\Definition\ConfigFormDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Framework\Plugin\Collection\PluginBasicCollection;
use Shopware\Framework\Plugin\Collection\PluginDetailCollection;
use Shopware\Framework\Plugin\Event\Plugin\PluginDeletedEvent;
use Shopware\Framework\Plugin\Event\Plugin\PluginWrittenEvent;
use Shopware\Framework\Plugin\Repository\PluginRepository;
use Shopware\Framework\Plugin\Struct\PluginBasicStruct;
use Shopware\Framework\Plugin\Struct\PluginDetailStruct;

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
            new TenantIdField(),
            new VersionField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
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
            (new OneToManyAssociationField('configForms', ConfigFormDefinition::class, 'plugin_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('paymentMethods', \Shopware\Checkout\Payment\PaymentMethodDefinition::class, 'plugin_id', false, 'id'))->setFlags(new CascadeDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return PluginDeletedEvent::class;
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
