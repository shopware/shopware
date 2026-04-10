<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class SearchFieldConfig
{
    public function __construct(
        private readonly string $field,
        private float $ranking,
        private readonly bool $tokenize,
        private readonly bool $andLogic = false,
        private readonly bool $prefixMatch = true
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

    public function isAndLogic(): bool
    {
        return $this->andLogic;
    }

    public function setRanking(float $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function usePrefixMatch(): bool
    {
        return $this->prefixMatch;
    }

    public function getFuzziness(string $token): string|int
    {
        // Disable fuzziness for numeric tokens or a serial of at least 3 digits
        if (is_numeric($token) || preg_match('/\d{3,}/', $token)) {
            return 0;
        }

        // (SKU-ish strings, e.g. "SD345-XYZ") - require exact match
        if (preg_match('/[A-Za-z].*\d|\d.*[A-Za-z]/', $token)) {
            return 0;
        }

        // Let AUTO:3,8 handle length thresholds (0 for ≤3, 1 for 4–8, 2 for ≥9)
        return 'AUTO:3,8';
    }
}
