<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

class TranslationsAssociationField extends SubresourceField implements AssociationInterface
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
        string $localField
    ) {
        parent::__construct($propertyName, $referenceClass, 'languageId');
        $this->loadInBasic = $loadInBasic;
        $this->localField = $localField;
        $this->referenceField = $referenceField;
    }

    public function getReferenceField(): string
    {
        return $this->referenceField;
    }

    public function getLocalField(): string
    {
        return $this->localField;
    }

    public function getExtractPriority(): int
    {
        return 90;
    }
}
