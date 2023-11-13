<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppShippingMethod;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppShippingMethodDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_shipping_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppShippingMethodEntity::class;
    }

    public function since(): ?string
    {
        return '6.5.7.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('app_name', 'appName'))->addFlags(new Required()),
            (new StringField('identifier', 'identifier'))->addFlags(new Required()),

            new FkField('app_id', 'appId', AppDefinition::class),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),

            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new Required()),
            new OneToOneAssociationField('shippingMethod', 'shipping_method_id', 'id', ShippingMethodDefinition::class, false),

            new FkField('original_media_id', 'originalMediaId', MediaDefinition::class),
            new ManyToOneAssociationField('originalMedia', 'original_media_id', MediaDefinition::class),
        ]);
    }
}
