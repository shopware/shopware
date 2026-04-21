<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @phpstan-type combination array<string, bool>
 */
#[Package('inventory')]
class AvailableCombinationResult extends Struct
{
    /**
     * @var combination
     */
    protected array $hashes = [];

    /**
     * @var combination
     */
    protected array $optionIds = [];

    /**
     * @var array<string, array<string>>
     */
    protected array $combinations = [];

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

        // When multiple source combinations collapse to the same hash (e.g. after
        // filtering out option ids that are not part of the configurator settings),
        // the combination must be considered available if any of the collapsed
        // variants is available.
        if (isset($this->combinationDetails[$hash])) {
            $available = $available || ($this->combinationDetails[$hash]['available'] ?? false);
        }

        $this->hashes[$hash] = true;
        $this->combinations[$hash] = $optionIds;
        $this->combinationDetails[$hash] = [
            'available' => $available,
        ];

        foreach ($optionIds as $id) {
            $this->optionIds[$id] = true;
        }
    }

    /**
     * Returns a new result that only contains option ids that exist in the given
     * allow-list. Variant combinations that reference option ids which are not
     * present as a configurator setting (for example because a row in
     * `product_configurator_setting` has been removed) are normalized to the
     * intersection with the known option ids so that the remaining configurator
     * options can still be resolved as combinable.
     *
     * @param array<string, mixed> $knownOptionIds map of known option id => any value
     */
    public function filterByKnownOptionIds(array $knownOptionIds): self
    {
        $filtered = new self();

        foreach ($this->combinations as $hash => $optionIds) {
            $intersected = [];
            foreach ($optionIds as $id) {
                if (isset($knownOptionIds[$id])) {
                    $intersected[] = $id;
                }
            }

            if ($intersected === []) {
                continue;
            }

            $filtered->addCombination(
                $intersected,
                (bool) ($this->combinationDetails[$hash]['available'] ?? false)
            );
        }

        return $filtered;
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

        return Hasher::hash($optionIds);
    }
}
