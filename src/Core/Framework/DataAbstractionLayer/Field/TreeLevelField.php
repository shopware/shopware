<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;

class TreeLevelField extends IntField implements StorageAware
{
    public function __construct(string $storageName, string $propertyName)
    {
        parent::__construct($storageName, $propertyName);

        $this->addFlags(new ReadOnly());
    }
}
