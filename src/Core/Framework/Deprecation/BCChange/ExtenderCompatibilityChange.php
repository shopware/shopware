<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * BC changes that can require action from code *extending* the annotated symbol.
 *
 * Whether action is actually required depends on how the subclass uses the symbol — for example
 * whether it overrides the annotated method or relies on the current class hierarchy. There is
 * usually no way to make an extending class compatible with both the current and the announced
 * declaration at the same time, so runtime deprecations are not possible for these changes and
 * static analysis of the extending code is the only ahead-of-time signal. Tooling should flag
 * subclasses and overrides that conflict with the announced change.
 */
#[Package('framework')]
interface ExtenderCompatibilityChange extends BCChangeAttribute
{
}
