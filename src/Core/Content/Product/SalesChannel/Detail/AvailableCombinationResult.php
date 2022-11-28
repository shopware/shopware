<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @package inventory
 */
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

    protected array $combinationDetails = [];

    /**
     * @param array<string> $optionIds
     */
    public function hasCombination(array $optionIds): bool
    {
        return isset($this->hashes[$this->calculateHash($optionIds)]);
    }

    /**
     * @deprecated tag:v6.5.0 - Parameter $available will be mandatory in future implementation
     *
     * @param array<string> $optionIds
     */
    public function addCombination(array $optionIds, bool $available = true): void
    {
        if (\func_num_args() < 2) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Second parameter $available of method `addCombination()` in `AvailableCombinationResult` will be required in v6.5.0.0.'
            );
        }

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

    public function getHashes(): array
    {
        return array_keys($this->hashes);
    }

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

        return md5((string) json_encode($optionIds));
    }
}
