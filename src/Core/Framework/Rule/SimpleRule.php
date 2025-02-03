<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal in v6.7.0
 */
#[Package('fundamentals@after-sales')]
class SimpleRule extends Rule
{
    final public const RULE_NAME = 'simple';

    /**
     * @internal
     */
    public function __construct(protected bool $match = true)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        return $this->match;
    }

    public function getConstraints(): array
    {
        return [
            'match' => RuleConstraints::bool(true),
        ];
    }
}
