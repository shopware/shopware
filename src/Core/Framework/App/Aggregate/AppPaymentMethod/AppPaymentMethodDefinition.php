<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppPaymentMethod;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
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
class AppPaymentMethodDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_payment_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppPaymentMethodCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppPaymentMethodEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('app_name', 'appName'))->addFlags(new Required()),
            (new StringField('identifier', 'identifier'))->addFlags(new Required()),
            new StringField('pay_url', 'payUrl'),
            new StringField('finalize_url', 'finalizeUrl'),
            new StringField('validate_url', 'validateUrl'),
            new StringField('capture_url', 'captureUrl'),
            new StringField('refund_url', 'refundUrl'),

            new FkField('app_id', 'appId', AppDefinition::class),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),

            new FkField('original_media_id', 'originalMediaId', MediaDefinition::class),
            new ManyToOneAssociationField('originalMedia', 'original_media_id', MediaDefinition::class),

            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required()),
            new OneToOneAssociationField('paymentMethod', 'payment_method_id', 'id', PaymentMethodDefinition::class, false),
        ]);
    }
}
