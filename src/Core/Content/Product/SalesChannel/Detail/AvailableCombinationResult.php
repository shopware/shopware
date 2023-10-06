<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @phpstan-type combination array<string, bool>
 */
#[Package('inventory')]
class AvailableCombinationResult extends Struct
{
    /**
     * @var combination
     */
    protected $hashes = [];

    /**
     * @var combination
     */
    protected $optionIds = [];

    /**
     * @var array<string, array<string>>
     */
    protected $combinations = [];

    /**
     * @var array<string, combination>
     */
    protected array $combinationDetails = [];

    /**
     * @param string[] $optionIds
     */
    public function hasCombination(array $optionIds): bool
    {
        return isset($this->hashes[$this->calculateHash($optionIds)]);
    }

    /**
     * @param string[] $optionIds
     */
    public function addCombination(array $optionIds, bool $available): void
    {
        $hash = $this->calculateHash($optionIds);
        $this->hashes[$hash] = true;
        $this->combinations[$hash] = $optionIds;
        $this->combinationDetails[$hash] = [
            'available' => $available,
        ];

        foreach ($optionIds as $id) {
            $this->optionIds[$id] = true;
        }
    }

    public function hasOptionId(string $optionId): bool
    {
        return isset($this->optionIds[$optionId]);
    }

    /**
     * @return array<string>
     */
    public function getHashes(): array
    {
        return array_keys($this->hashes);
    }

    /**
     * @return array<string, array<string>>
     */
    public function getCombinations(): array
    {
        return $this->combinations;
    }

    /**
     * @param array<string> $optionIds
     */
    public function isAvailable(array $optionIds): bool
    {
        return $this->combinationDetails[$this->calculateHash($optionIds)]['available'] ?? false;
    }

    /**
     * @param array<string> $optionIds
     */
    private function calculateHash(array $optionIds): string
    {
        $optionIds = array_values($optionIds);
        sort($optionIds);

        return md5((string) json_encode($optionIds, \JSON_THROW_ON_ERROR));
    }
}
