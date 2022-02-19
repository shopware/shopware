<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

/**
 * AppSpecific hooks are only executed for the given AppId, e.g. app lifecycle hooks
 *
 * @internal
 */
interface AppSpecificHook
{
    public function getAppId(): string;
}
