<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Defines that the data of this field is stored in an Entity::$extension and are not part of the struct itself.
 */
class Extension extends Flag
{
    public function parse(): \Generator
    {
        yield 'extension' => true;
    }
}
