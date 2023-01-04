<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
interface DeprecatedHook
{
    public static function getDeprecationNotice(): string;
}
