<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

class AvailableCombinationResult
{
    /**
     * @var array
     */
    protected $hashes;

    /**
     * @var string[]
     */
    protected $optionIds;

    public function __construct(array $hashes, array $optionIds)
    {
        $this->hashes = $hashes;
        $this->optionIds = $optionIds;
    }

    public function hasCombination(array $optionIds): bool
    {
        $optionIds = array_values($optionIds);
        sort($optionIds);

        $hash = md5(json_encode($optionIds));

        return $this->hasHash($hash);
    }

    public function hasHash(string $hash): bool
    {
        return isset($this->hashes[$hash]);
    }

    public function hasOptionId(string $optionId): bool
    {
        return isset($this->optionIds[$optionId]);
    }

    public function getHashes(): array
    {
        return $this->hashes;
    }

    public function setHashes(array $hashes): void
    {
        $this->hashes = $hashes;
    }

    public function getOptionIds(): array
    {
        return $this->optionIds;
    }

    public function setOptionIds(array $optionIds): void
    {
        $this->optionIds = $optionIds;
    }
}
