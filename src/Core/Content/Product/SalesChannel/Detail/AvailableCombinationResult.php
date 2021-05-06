<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Framework\Struct\Struct;

class AvailableCombinationResult extends Struct
{
    /**
     * @var array
     */
    protected $hashes = [];

    /**
     * @var array
     */
    protected $optionIds = [];

    /**
     * @var array
     */
    protected $combinations = [];

    public function hasCombination(array $optionIds): bool
    {
        return isset($this->hashes[$this->calculateHash($optionIds)]);
    }

    public function addCombination(array $optionIds): void
    {
        $hash = $this->calculateHash($optionIds);
        $this->hashes[$hash] = true;
        $this->combinations[$hash] = $optionIds;

        foreach ($optionIds as $id) {
            $this->optionIds[$id] = true;
        }
    }

    public function hasOptionId(string $optionId): bool
    {
        return isset($this->optionIds[$optionId]);
    }

    public function getHashes(): array
    {
        return array_keys($this->hashes);
    }

    public function getCombinations(): array
    {
        return $this->combinations;
    }

    private function calculateHash(array $optionIds): string
    {
        $optionIds = array_values($optionIds);
        sort($optionIds);

        return md5((string) json_encode($optionIds));
    }
}
