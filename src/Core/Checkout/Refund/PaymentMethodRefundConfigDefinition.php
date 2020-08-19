<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PaymentMethodRefundConfigDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'payment_method_refund_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentMethodRefundConfigCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentMethodRefundConfigEntity::class;
    }

    public function since(): ?string
    {
        return '6.3.4.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required()),
            (new JsonField('options', 'options', [], []))->addFlags(new Required()),

            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, 'id', false),
        ]);
    }
}
