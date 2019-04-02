<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

abstract class Flag
{
    /**
     * Returns a readable name for the flag
     */
    abstract public function parse(): \Generator;
}
