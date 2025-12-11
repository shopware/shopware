<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @description This flag indicates that the field is write-once and then read-only
 */
#[Package('framework')]
class Immutable extends Flag
{
    public function parse(): \Generator
    {
        yield 'immutable' => true;
    }
}
