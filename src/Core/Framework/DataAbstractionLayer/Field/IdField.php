<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\IdGenerator\RamseyGenerator;

class IdField extends Field implements StorageAware
{
    /**
     * @var string
     */
    protected $storageName;

    /**
     * @var string
     */
    protected $generatorClass;

    public function __construct(string $storageName, string $propertyName, string $generatorClass = RamseyGenerator::class)
    {
        $this->storageName = $storageName;
        $this->generatorClass = $generatorClass;
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }
}
