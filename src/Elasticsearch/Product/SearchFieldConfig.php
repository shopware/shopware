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
        $fuzziness = $this->tokenize ? 'auto' : 1;

        if (is_numeric($token) || preg_match('/\d{3,}/', $token)) {
            $fuzziness = 0; // Disable fuzziness for numeric tokens or a serial of at least 3 digits
        }

        return $fuzziness;
    }
}
