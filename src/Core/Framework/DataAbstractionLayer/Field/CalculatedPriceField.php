<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CalculatedPriceFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CalculatedPriceField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        $propertyMapping = [
            (new FloatField('unitPrice', 'unitPrice'))->addFlags(new Required()),
            (new FloatField('totalPrice', 'totalPrice'))->addFlags(new Required()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
            (new JsonField('calculatedTaxes', 'calculatedTaxes'))->addFlags(new Required()),
            (new JsonField('taxRules', 'taxRules'))->addFlags(new Required()),
            new JsonField('referencePrice', 'referencePrice'),
            new JsonField('listPrice', 'listPrice', [
                new FloatField('price', 'price'),
                new FloatField('discount', 'discount'),
                new FloatField('percentage', 'percentage'),
            ]),
            new JsonField('regulationPrice', 'regulationPrice', [
                new FloatField('price', 'price'),
            ]),
        ];

        parent::__construct($storageName, $propertyName, $propertyMapping);
    }

    protected function getSerializerClass(): string
    {
        return CalculatedPriceFieldSerializer::class;
    }
}
