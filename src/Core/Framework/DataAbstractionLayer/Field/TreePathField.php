<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;

class TreePathField extends LongTextField implements StorageAware
{
    private $pathField;

    public function __construct(string $storageName, string $propertyName, string $pathField = 'id')
    {
        $this->pathField = $pathField;
        parent::__construct($storageName, $propertyName);

        $this->addFlags(new ReadOnly());
    }

    public function getPathField(): string
    {
        return $this->pathField;
    }
}
