<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Container\XorRule;

/**
 * Placeholder used when an entity references a rule condition type that is no longer registered
 * (e.g. a plugin contributing it has been uninstalled).
 *
 * The original, unresolvable rule payload is kept verbatim and re-emitted on serialization, so it
 * round-trips losslessly through reads, order versioning and normal saves, and is restored once the
 * contributing plugin is reinstalled. Recalculation recomputes the cart, so a discount whose
 * placeholder no longer matches (its match() is always false) may be dropped rather than preserved.
 *
 * `match()` always returns false. Note this only yields a "fail-closed" outcome for a standalone
 * condition or inside an {@see AndRule}: inside an
 * {@see OrRule} or
 * {@see XorRule} the container can still match via its other
 * branches, and inside a {@see NotRule} the result is inverted
 * to always-match.
 *
 * @internal
 *
 * @final
 */
#[Package('fundamentals@after-sales')]
class UnknownConditionRule extends Rule
{
    final public const RULE_NAME = 'unknown_condition';

    /**
     * @param array<string, mixed> $originalPayload the original (unresolvable) rule payload, kept verbatim so writes round-trip losslessly
     */
    public function __construct(private array $originalPayload = [])
    {
        parent::__construct();
    }

    /**
     * Returns the rule condition name that could not be resolved (for diagnostics / logging).
     */
    public function getOriginalName(): string
    {
        $name = $this->originalPayload['_name'] ?? null;

        return \is_string($name) ? $name : '';
    }

    public function match(RuleScope $scope): bool
    {
        return false;
    }

    public function getConstraints(): array
    {
        return [];
    }

    /**
     * Re-emit the original rule payload verbatim, so encoding (and thus order versioning and
     * recalculation) preserves the stored value instead of overwriting it with this placeholder.
     */
    public function jsonSerialize(): array
    {
        return $this->originalPayload;
    }
}
