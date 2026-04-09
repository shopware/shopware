<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\RequestStackDirty;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class StackDepth
{
    public int $before = 0;
}
