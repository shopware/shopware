<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TaxFreeConfigFieldSerializer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class TaxFreeConfigField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        parent::__construct($storageName, $propertyName, [
            (new BoolField('enabled', 'enabled'))->addFlags(new Required()),
            (new StringField('currencyId', 'currencyId'))->addFlags(new Required()),
            (new FloatField('amount', 'amount'))->addFlags(new Required()),
        ]);
    }

    protected function getSerializerClass(): string
    {
        return TaxFreeConfigFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return JsonFieldAccessorBuilder::class;
    }
}
