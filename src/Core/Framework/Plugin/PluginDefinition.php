<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Internal;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;

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
            new StringField('composer_name', 'composerName'),
            (new JsonField('autoload', 'autoload'))->addFlags(new Required()),
            new BoolField('active', 'active'),
            new BoolField('managed_by_composer', 'managedByComposer'),
            new StringField('path', 'path'),
            new StringField('author', 'author'),
            new StringField('copyright', 'copyright'),
            new StringField('license', 'license'),
            (new StringField('version', 'version'))->addFlags(new Required()),
            new StringField('upgrade_version', 'upgradeVersion'),
            new DateField('installed_at', 'installedAt'),
            new DateField('upgraded_at', 'upgradedAt'),
            (new BlobField('icon', 'iconRaw'))->addFlags(new Internal()),
            (new StringField('icon', 'icon'))->addFlags(new WriteProtected(), new Deferred()),

            new TranslatedField('label'),
            new TranslatedField('description'),
            new TranslatedField('manufacturerLink'),
            new TranslatedField('supportLink'),
            new TranslatedField('changelog'),
            new TranslatedField('customFields'),

            (new TranslationsAssociationField(PluginTranslationDefinition::class, 'plugin_id'))->addFlags(new Required(), new CascadeDelete()),
            new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'plugin_id', 'id'),
        ]);
    }
}
