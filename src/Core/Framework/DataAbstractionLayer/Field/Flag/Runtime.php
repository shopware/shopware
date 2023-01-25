<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * Defines that the data of the field will be loaded at runtime by an event subscriber or other class.
 * Used in entity extensions for plugins or not directly fetchable associations.
 */
#[Package('core')]
class Runtime extends Flag
{
    public function __construct(private readonly array $dependsOn = [])
    {
    }

    public function parse(): \Generator
    {
        yield 'runtime' => true;
    }

    public function getDepends(): array
    {
        return $this->dependsOn;
    }
}
