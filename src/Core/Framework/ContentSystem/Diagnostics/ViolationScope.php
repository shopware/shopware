<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum ViolationScope: string
{
    case Intrinsic = 'intrinsic';
    case Binding = 'binding';
}
