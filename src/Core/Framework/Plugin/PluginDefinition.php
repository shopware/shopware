<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;

#[Package('framework')]
class PluginDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'plugin';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PluginCollection::class;
    }

    public function getEntityClass(): string
    {
        return PluginEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of a plugin.'),
            (new StringField('base_class', 'baseClass'))->addFlags(new Required())->setDescription('Name of the new class that extends from Shopware\'s abstract Plugin class.'),
            (new StringField('name', 'name'))->addFlags(new Required())->setDescription('Unique name of the plugin.'),
            (new StringField('composer_name', 'composerName'))->setDescription('Name of the composer package name.'),
            (new JsonField('autoload', 'autoload'))->addFlags(new Required())->setDescription('This ensures to automatically load all class files of a project before using them.'),
            (new BoolField('active', 'active'))->setDescription('When boolean value is `true`, the plugin is available.'),
            (new BoolField('managed_by_composer', 'managedByComposer'))->setDescription('A property to check whether it is installed via composer or not.'),
            (new StringField('path', 'path'))->setDescription('A relative URL to the plugin.'),
            (new StringField('author', 'author'))->setDescription('Creator of the plugin.'),
            (new StringField('copyright', 'copyright'))->setDescription('Legal rights on the created plugin.'),
            (new StringField('license', 'license'))->setDescription('Software license\'s like MIT, etc.'),
            (new StringField('version', 'version'))->addFlags(new Required())->setDescription('Version of the plugin.'),
            (new StringField('upgrade_version', 'upgradeVersion'))->setDescription('Update version available for upgrading plugins.'),
            (new DateTimeField('installed_at', 'installedAt'))->setDescription('Date and time when the plugin was installed.'),
            (new DateTimeField('upgraded_at', 'upgradedAt'))->setDescription('Date and time when the plugin was upgraded.'),
            (new BlobField('icon', 'iconRaw'))->removeFlag(ApiAware::class),
            (new StringField('icon', 'icon'))->addFlags(new WriteProtected(), new Runtime()),
            new TranslatedField('label'),
            new TranslatedField('description'),
            new TranslatedField('manufacturerLink'),
            new TranslatedField('supportLink'),
            new TranslatedField('customFields'),

            (new TranslationsAssociationField(PluginTranslationDefinition::class, 'plugin_id'))->addFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'plugin_id', 'id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
