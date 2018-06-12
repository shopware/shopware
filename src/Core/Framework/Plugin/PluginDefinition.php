<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\Plugin\Collection\PluginBasicCollection;
use Shopware\Core\Framework\Plugin\Struct\PluginBasicStruct;
use Shopware\Core\System\Config\ConfigFormDefinition;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
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
            (new OneToManyAssociationField('paymentMethods', \Shopware\Core\Checkout\Payment\PaymentMethodDefinition::class, 'plugin_id', false, 'id'))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getBasicCollectionClass(): string
    {
        return PluginBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return PluginBasicStruct::class;
    }
}
