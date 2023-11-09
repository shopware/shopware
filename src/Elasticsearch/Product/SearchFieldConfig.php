<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class SearchFieldConfig
{
    public function __construct(
        private readonly string $field,
        private readonly int $ranking,
        private readonly bool $tokenize
    ) {
    }

    public function tokenize(): bool
    {
        return $this->tokenize;
    }

    public function getRanking(): int
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
}
