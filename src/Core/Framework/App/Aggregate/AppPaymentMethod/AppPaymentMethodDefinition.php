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
#[Package('framework')]
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
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of app\'s payment method.'),
            (new StringField('app_name', 'appName'))->addFlags(new Required())->setDescription('Name of the app.'),
            (new StringField('identifier', 'identifier'))->addFlags(new Required())->setDescription('It is a unique identity of an AppPaymentMethod.'),
            (new StringField('pay_url', 'payUrl'))->setDescription('A URL sending the pay request.'),
            (new StringField('finalize_url', 'finalizeUrl'))->setDescription('A URL that redirects the user back to the shop.'),
            (new StringField('validate_url', 'validateUrl'))->setDescription('A validate URL confirms the authenticity of a payment reference when accessed.'),
            (new StringField('capture_url', 'captureUrl'))->setDescription('A capture URL allows the payments to be processed and completed once validated.'),
            (new StringField('refund_url', 'refundUrl'))->setDescription('A refund URL is used to initiate the refund process for a purchase.'),
            (new StringField('recurring_url', 'recurringUrl'))->setDescription('A URL to payment to handle recurring orders like subscriptions.'),

            (new FkField('app_id', 'appId', AppDefinition::class))->setDescription('Unique identity of app.'),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),

            (new FkField('original_media_id', 'originalMediaId', MediaDefinition::class))->setDescription('Unique identity of original media.'),
            new ManyToOneAssociationField('originalMedia', 'original_media_id', MediaDefinition::class),

            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required())->setDescription('Unique identity of payment method.'),
            new OneToOneAssociationField('paymentMethod', 'payment_method_id', 'id', PaymentMethodDefinition::class, false),
        ]);
    }
}
