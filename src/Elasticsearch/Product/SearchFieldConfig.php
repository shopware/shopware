<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class SearchFieldConfig
{
    private float $ranking;

    public function __construct(
        private readonly string $field,
        int|float $ranking,
        private readonly bool $tokenize,
        private readonly bool $andLogic = false
    ) {
        if (Feature::isActive('v6.7.0.0') && \is_int($ranking)) {
            Feature::throwException('v6.7.0.0', 'The ranking property in SearchFieldConfig is now a float.');
        }

        $this->ranking = (float) $ranking;
    }

    public function tokenize(): bool
    {
        return $this->tokenize;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change -  Return type will be changed to float
     */
    public function getRanking(): int|float
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

    public function isAndLogic(): bool
    {
        return $this->andLogic;
    }

    public function setRanking(float $ranking): void
    {
        $this->ranking = $ranking;
    }
}
