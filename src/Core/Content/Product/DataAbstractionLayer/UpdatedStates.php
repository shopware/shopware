<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
final class UpdatedStates extends Struct
{
    private string $id;

    /**
     * @var string[]
     */
    private array $oldStates;

    /**
     * @var string[]
     */
    private array $newStates;

    /**
     * @param string[] $oldStates
     * @param string[] $newStates
     */
    public function __construct(string $id, array $oldStates, array $newStates)
    {
        $this->id = $id;
        $this->oldStates = $oldStates;
        $this->newStates = $newStates;
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
