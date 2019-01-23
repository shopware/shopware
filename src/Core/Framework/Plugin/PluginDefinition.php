<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class PluginDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'plugin';
    }

    public static function getCollectionClass(): string
    {
        return PluginCollection::class;
    }

    public static function getEntityClass(): string
    {
        return PluginEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('label', 'label'))->addFlags(new Required()),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            (new StringField('version', 'version'))->addFlags(new Required()),
            (new BoolField('capability_update', 'capabilityUpdate'))->addFlags(new Required()),
            (new BoolField('capability_install', 'capabilityInstall'))->addFlags(new Required()),
            (new BoolField('capability_enable', 'capabilityEnable'))->addFlags(new Required()),
            (new BoolField('capability_secure_uninstall', 'capabilitySecureUninstall'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new LongTextField('description_long', 'descriptionLong'),
            new CreatedAtField(),
            new UpdatedAtField(),
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
            new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'plugin_id', false, 'id'),
        ]);
    }
}
