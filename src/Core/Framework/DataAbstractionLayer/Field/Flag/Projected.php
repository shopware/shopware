<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Projection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Projected extends Flag
{
    public function __construct(public readonly Projection $projection)
    {
    }

    public function parse(): \Generator
    {
        yield 'projected' => true;
    }
}
