<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;

/**
 * Fail-closed placeholder used when an entity references a rule condition type
 * that is no longer registered (e.g. a plugin contributing it has been uninstalled).
 *
 * @internal
 *
 * @final
 */
#[Package('fundamentals@after-sales')]
class MissingConditionRule extends Rule
{
    /**
     * The placeholder is intentionally not registered, so write validation rejects this name.
     */
    final public const RULE_NAME = '__missing_condition';

    public function __construct(protected string $originalName = '')
    {
        parent::__construct();
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function match(RuleScope $scope): bool
    {
        return false;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
