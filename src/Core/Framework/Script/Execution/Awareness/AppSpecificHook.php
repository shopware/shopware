<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\Framework\Log\Package;

/**
 * AppSpecific hooks are only executed for the given AppId, e.g. app lifecycle hooks
 *
 * @internal
 */
#[Package('core')]
interface AppSpecificHook
{
    public function getAppId(): string;
}
