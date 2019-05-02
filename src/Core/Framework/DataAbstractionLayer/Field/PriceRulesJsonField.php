<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceRuleFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceRulesJsonFieldSerializer;

class PriceRulesJsonField extends JsonField
{
    public function __construct(string $storageName, string $propertyName, array $propertyMapping = [])
    {
        parent::__construct($storageName, $propertyName, $propertyMapping);
        $this->addFlags(new WriteProtected());
    }

    protected function getSerializerClass(): string
    {
        return PriceRulesJsonFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return PriceRuleFieldAccessorBuilder::class;
    }
}
