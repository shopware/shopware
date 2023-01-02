<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

/**
 * @internal
 * @package core
 */
interface DeprecatedHook
{
    public static function getDeprecationNotice(): string;
}
