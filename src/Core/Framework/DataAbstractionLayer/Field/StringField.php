<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;

class StringField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var int
     */
    private $maxLength;

    public function __construct(string $storageName, string $propertyName, int $maxLength = 255)
    {
        $this->storageName = $storageName;
        parent::__construct($propertyName);
        $this->maxLength = $maxLength;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    protected function getSerializerClass(): string
    {
        return StringFieldSerializer::class;
    }
}
