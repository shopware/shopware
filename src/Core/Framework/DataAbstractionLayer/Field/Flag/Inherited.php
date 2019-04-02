<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Defines that the data of this field can be inherited by the parent record
 */
class Inherited extends Flag
{
    public function parse(): \Generator
    {
        yield 'inherited' => true;
    }
}
