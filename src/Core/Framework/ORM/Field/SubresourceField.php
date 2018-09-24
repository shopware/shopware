<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldException\MalformatDataException;

class SubresourceField extends Field
{
    /**
     * @var string|EntityDefinition
     */
    protected $referenceClass;

    /**
     * @var string
     */
    protected $possibleKey;

    public function __construct(string $propertyName, string $referenceClass, ?string $possibleKey = null)
    {
        $this->referenceClass = $referenceClass;
        $this->possibleKey = $possibleKey;
        parent::__construct($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, \count($value) - 1);

        foreach ($value as $keyValue => $subresources) {
            if (!\is_array($subresources)) {
                throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
            }

            if ($this->possibleKey && !$isNumeric) {
                $subresources[$this->possibleKey] = $keyValue;
            }

            $this->writeResource->extract(
                $subresources,
                $this->referenceClass,
                $this->exceptionStack,
                $this->commandQueue,
                $this->writeContext,
                $this->fieldExtenderCollection,
                $this->path . '/' . $key . '/' . $keyValue
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
    }
}
