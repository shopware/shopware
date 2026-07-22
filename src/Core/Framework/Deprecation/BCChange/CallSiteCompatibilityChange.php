<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * BC changes that can require action from code *calling* the annotated method.
 *
 * This includes calls from an extending class via `parent::`.
 *
 * Whether action is actually required depends on how the call site uses the method — for example
 * which argument types it passes or whether it uses named arguments. Tooling should inspect the
 * concrete usage and only flag call sites that conflict with the announced change.
 */
#[Package('framework')]
interface CallSiteCompatibilityChange extends BCChangeAttribute
{
}
