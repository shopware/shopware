<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

class AvailableCombinationResult
{
    /**
     * @var array
     */
    protected $hashes = [];

    /**
     * @var array
     */
    protected $optionIds = [];

    public function hasCombination(array $optionIds): bool
    {
        $optionIds = array_values($optionIds);
        sort($optionIds);

        $hash = md5(json_encode($optionIds));

        return isset($this->hashes[$hash]);
    }

    public function addCombination(array $optionIds): void
    {
        $optionIds = array_values($optionIds);
        sort($optionIds);

        $hash = md5(json_encode($optionIds));

        $this->hashes[$hash] = true;

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
        return $this->hashes;
    }
}
