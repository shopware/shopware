<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @description Includes the field's current database value in the EntityExistence state before write
 */
#[Package('framework')]
class ExistenceAware extends Flag
{
    public function parse(): \Generator
    {
        yield 'existence_aware' => true;
    }
}
