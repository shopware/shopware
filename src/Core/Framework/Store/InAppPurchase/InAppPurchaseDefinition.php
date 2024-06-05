<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase;

use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginDefinition;

/**
 * @internal
 */
#[Package('core')]
class InAppPurchaseDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'in_app_purchase';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return InAppPurchaseEntity::class;
    }

    public function getCollectionClass(): string
    {
        return InAppPurchaseCollection::class;
    }

    public function since(): ?string
    {
        return '6.6.6.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new ReadProtection(Context::SYSTEM_SCOPE),
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('identifier', 'identifier'))->addFlags(new Required()),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            (new DateTimeField('expires_at', 'expiresAt'))->addFlags(new Required()),

            new FkField('app_id', 'appId', AppDefinition::class),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),

            new FkField('plugin_id', 'pluginId', PluginDefinition::class),
            new ManyToOneAssociationField('plugin', 'plugin_id', PluginDefinition::class),
        ]);
    }
}
