<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ListingPriceFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListingPriceFieldSerializer;

class ListingPriceField extends JsonField
{
    public function __construct(string $storageName, string $propertyName, array $propertyMapping = [])
    {
        parent::__construct($storageName, $propertyName, $propertyMapping);
        $this->addFlags(new WriteProtected());
    }

    protected function getSerializerClass(): string
    {
        return ListingPriceFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return ListingPriceFieldAccessorBuilder::class;
    }
}
