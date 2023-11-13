<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
final class KernelListenerPriorities
{
    public const KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_PRE = -1;

    public const KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE = -2;

    public const KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_POST = -3;

    public const KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE = -9;

    public const KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE = -10;

    public const KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST = -11;

    public const KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_PRE = -19;

    public const KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE = -20;

    public const KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST = -21;

    private function __construct()
    {
    }
}
