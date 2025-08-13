<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * Defines that the data of this field is stored in custom fields and are not part of the struct itself.
 */
#[Package('framework')]
class CustomField extends Flag
{
    public function parse(): \Generator
    {
        yield 'custom_flag' => true;
    }
}
