<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

interface AttributeServiceInterface
{
    public function getAttributeField(string $attributeName): Field;
}
