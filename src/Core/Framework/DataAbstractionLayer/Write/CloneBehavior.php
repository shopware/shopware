<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

class CloneBehavior
{
    /**
     * @var array
     */
    private $overwrites;

    /**
     * @var bool
     */
    private $cloneChildren;

    public function __construct(array $overwrites = [], bool $cloneChildren = true)
    {
        $this->overwrites = $overwrites;
        $this->cloneChildren = $cloneChildren;
    }

    public function getOverwrites(): array
    {
        return $this->overwrites;
    }

    public function cloneChildren(): bool
    {
        return $this->cloneChildren;
    }
}
