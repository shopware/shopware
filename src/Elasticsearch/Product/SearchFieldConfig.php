<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class SearchFieldConfig
{
    private ?bool $isTextField = null;

    public function __construct(
        private string $field,
        private float $ranking,
        private readonly bool $tokenize,
        private readonly bool $andLogic = false,
        private readonly bool $translatedField = false,
        private readonly ?Field $fieldDefinition = null
    ) {
    }

    public function tokenize(): bool
    {
        return $this->tokenize;
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function isCustomField(): bool
    {
        return str_contains($this->field, 'customFields');
    }

    public function getFieldDefinition(): ?Field
    {
        return $this->fieldDefinition;
    }

    public function isTranslatedField(): bool
    {
        return $this->translatedField;
    }

    public function isTextField(): bool
    {
        if ($this->isTextField) {
            return $this->isTextField;
        }

        $this->isTextField = $this->fieldDefinition instanceof StringField || $this->fieldDefinition instanceof LongTextField || $this->fieldDefinition instanceof ListField;

        return $this->isTextField;
    }

    public function isAndLogic(): bool
    {
        return $this->andLogic;
    }
}
