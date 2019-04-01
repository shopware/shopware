<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;

class PriceRulesJsonField extends JsonField
{
    public function __construct(string $storageName, string $propertyName, array $propertyMapping = [])
    {
        parent::__construct($storageName, $propertyName, $propertyMapping);
        $this->addFlags(new WriteProtected());
    }
}
