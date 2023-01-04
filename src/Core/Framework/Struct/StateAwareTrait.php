<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
trait StateAwareTrait
{
    /**
     * @var array<string>
     */
    private array $states = [];

    public function addState(string ...$states): void
    {
        foreach ($states as $state) {
            $this->states[$state] = $state;
        }
    }

    public function removeState(string $state): void
    {
        unset($this->states[$state]);
    }

    public function hasState(string ...$states): bool
    {
        foreach ($states as $state) {
            if (isset($this->states[$state])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    public function getStates(): array
    {
        return array_keys($this->states);
    }
}
