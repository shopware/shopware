<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class Flag
{
    /**
     * Returns a readable name for the flag
     */
    abstract public function parse(): \Generator;
}
