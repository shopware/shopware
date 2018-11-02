<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;

class OneToManyAssociationField extends Field implements AssociationInterface
{
    use AssociationTrait;

    /**
     * @var string
     */
    protected $localField;

    /**
     * @var string
     */
    protected $referenceField;

    public function __construct(
        string $propertyName,
        string $referenceClass,
        string $referenceField,
        bool $loadInBasic,
        string $localField = 'id'
    ) {
        parent::__construct($propertyName);
        $this->loadInBasic = $loadInBasic;
        $this->localField = $localField;
        $this->referenceField = $referenceField;
        $this->referenceClass = $referenceClass;
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getLocalField(): string
    {
        return $this->localField;
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

        foreach ($value as $keyValue => $subresources) {
            if (!\is_array($subresources)) {
                throw new MalformatDataException($this->path . '/' . $key, 'Value must be an array.');
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
