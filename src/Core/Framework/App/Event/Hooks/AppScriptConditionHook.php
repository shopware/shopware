<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event\Hooks;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\TraceHook;

/**
 * @internal
 */
#[Package('core')]
class AppScriptConditionHook extends TraceHook
{
    public static function getServiceIds(): array
    {
        return [];
    }

    public function getName(): string
    {
        return 'rule-conditions';
    }
}
