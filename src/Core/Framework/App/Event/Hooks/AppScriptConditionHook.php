<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event\Hooks;

use Shopware\Core\Framework\Script\Execution\TraceHook;

/**
 * @internal
 */
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
