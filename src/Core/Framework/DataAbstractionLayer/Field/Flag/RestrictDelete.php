<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * Associated data with this flag, restricts the delete of the entity in case that a record with the primary key exists.
 */
#[Package('core')]
class RestrictDelete extends Flag
{
    public function parse(): \Generator
    {
        yield 'restrict_delete' => true;
    }
}
