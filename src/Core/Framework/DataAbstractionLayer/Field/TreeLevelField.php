<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;
use Shopware\Core\Framework\SourceContext;

class TreeLevelField extends IntField
{
    public function __construct(string $storageName, string $propertyName)
    {
        parent::__construct($storageName, $propertyName);

        $this->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM));
    }
}
