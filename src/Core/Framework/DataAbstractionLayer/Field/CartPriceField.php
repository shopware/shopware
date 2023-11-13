<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CartPriceField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        $propertyMapping = [
            (new FloatField('netPrice', 'netPrice'))->addFlags(new Required()),
            (new FloatField('totalPrice', 'totalPrice'))->addFlags(new Required()),
            (new JsonField('calculatedTaxes', 'calculatedTaxes'))->addFlags(new Required()),
            (new JsonField('taxRules', 'taxRules'))->addFlags(new Required()),
            (new FloatField('positionPrice', 'positionPrice'))->addFlags(new Required()),
            (new FloatField('rawTotal', 'rawTotal'))->setFlags(new Required()),
            (new StringField('taxStatus', 'taxStatus'))->addFlags(new Required()),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }

    protected function getSerializerClass(): string
    {
        return CartPriceFieldSerializer::class;
    }
}
