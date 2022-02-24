<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Defines that the data of the field will be loaded at runtime by an event subscriber or other class.
 * Used in entity extensions for plugins or not directly fetchable associations.
 */
class Runtime extends Flag
{
    private array $dependsOn;

    public function __construct(array $dependsOn = [])
    {
        $this->dependsOn = $dependsOn;
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
