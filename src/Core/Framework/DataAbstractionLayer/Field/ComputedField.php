<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Internal;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;

class ComputedField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    public function __construct(string $storageName, string $propertyName)
    {
        parent::__construct($propertyName);

        $this->storageName = $storageName;

        $this->addFlags(new ReadOnly(), new Internal());
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }
}
