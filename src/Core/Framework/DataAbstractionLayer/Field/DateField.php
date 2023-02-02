<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer;

class DateField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    public function __construct(string $storageName, string $propertyName)
    {
        $this->storageName = $storageName;
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    protected function getSerializerClass(): string
    {
        return DateFieldSerializer::class;
    }
}
