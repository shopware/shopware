<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;

class ReferenceField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $referenceField;

    /**
     * @var string
     */
    private $referenceClass;

    /**
     * @var string
     */
    private $storageName;

    public function __construct(string $storageName, string $propertyName, string $referenceField, string $referenceClass)
    {
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        $this->referenceClass = $referenceClass;
        parent::__construct($propertyName);
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getExtractPriority(): int
    {
        return 80;
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($this->path, 'Expected array');
        }

        $this->writeResource->extract(
            $value,
            $this->referenceClass,
            $this->exceptionStack,
            $this->commandQueue,
            $this->writeContext,
            $this->fieldExtenderCollection,
            $this->path . '/' . $key
        );

        $id = $this->writeContext->get($this->referenceClass, $this->referenceField);

        $fkField = $this->definition::getFields()->getByStorageName($this->storageName);

        yield $fkField->getPropertyName() => $id;
    }
}
