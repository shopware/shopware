<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

interface AttributeServiceInterface
{
    public function getAttributeTypeMap(): array;

    public function getAttributeType(string $attributeName): ?string;
}
