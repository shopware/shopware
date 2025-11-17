<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
 */
#[Package('framework')]
final class UpdatedStates extends Struct
{
    /**
     * @param string[] $oldStates
     * @param string[] $newStates
     */
    public function __construct(
        private readonly string $id,
        private readonly array $oldStates,
        private array $newStates
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getOldStates(): array
    {
        return $this->oldStates;
    }

    /**
     * @return string[]
     */
    public function getNewStates(): array
    {
        return $this->newStates;
    }

    /**
     * @param string[] $newStates
     */
    public function setNewStates(array $newStates): void
    {
        $this->newStates = $newStates;
    }
}
