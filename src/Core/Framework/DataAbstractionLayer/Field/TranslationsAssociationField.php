<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class TranslationsAssociationField extends Field implements AssociationInterface
{
    use AssociationTrait;

    public const PRIORITY = 90;

    /**
     * @var string
     */
    protected $localField;

    /**
     * @var string
     */
    protected $referenceField;

    public function __construct(
        string $referenceClass,
        string $propertyName = 'translations',
        bool $loadInBasic = false,
        string $localField = 'id'
    ) {
        parent::__construct($propertyName);
        $this->loadInBasic = $loadInBasic;
        $this->localField = $localField;
        $this->referenceField = 'languageId';
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

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }
}
