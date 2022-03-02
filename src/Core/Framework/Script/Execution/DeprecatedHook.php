<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

/**
 * @internal
 */
interface DeprecatedHook
{
    public static function getDeprecationNotice(): string;
}
