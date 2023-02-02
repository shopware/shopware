<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\BlobFieldSerializer;

class BlobField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    public function __construct(string $storageName, string $propertyName)
    {
        parent::__construct($propertyName);

        $this->storageName = $storageName;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    protected function getSerializerClass(): string
    {
        return BlobFieldSerializer::class;
    }
}
