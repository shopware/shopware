<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Flag a text column that an empty string should not be considered as null
 */
class AllowEmptyString extends Flag
{
    public function parse(): \Generator
    {
        yield 'allow_empty_string' => true;
    }
}
