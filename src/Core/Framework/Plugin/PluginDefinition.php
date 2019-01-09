<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslationDefinition;

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
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new StringField('author', 'author'),
            new StringField('copyright', 'copyright'),
            new StringField('license', 'license'),
            (new StringField('version', 'version'))->addFlags(new Required()),
            new StringField('upgrade_version', 'upgradeVersion'),
            new DateField('installed_at', 'installedAt'),
            new DateField('upgraded_at', 'upgradedAt'),

            new TranslatedField('label'),
            new TranslatedField('description'),
            new TranslatedField('manufacturerLink'),
            new TranslatedField('supportLink'),
            new TranslatedField('changelog'),

            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField(PluginTranslationDefinition::class, 'plugin_id'))->addFlags(new Required(), new CascadeDelete()),
            new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'plugin_id', false, 'id'),
        ]);
    }
}
