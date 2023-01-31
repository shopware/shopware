<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Log\Package;

/**
 * Only to be used by "dummy" hooks for the sole purpose of tracing
 *
 * @internal
 */
#[Package('core')]
abstract class TraceHook extends Hook
{
}
