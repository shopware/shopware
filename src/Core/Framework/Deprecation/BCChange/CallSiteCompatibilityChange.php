<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * BC changes that can require action from code *calling* the annotated symbol.
 *
 * Whether action is actually required depends on how the call site uses the symbol — for example
 * which argument types it passes, whether it uses named arguments, or which exceptions it catches.
 * Tooling should inspect the concrete usage and only flag call sites that conflict with the
 * announced change.
 */
#[Package('framework')]
interface CallSiteCompatibilityChange extends BCChangeAttribute
{
}
