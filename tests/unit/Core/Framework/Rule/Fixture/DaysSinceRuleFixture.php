<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Fixture;

use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 *
 * @internal
 */
class DaysSinceRuleFixture extends DaysSinceRule
{
    final public const RULE_NAME = 'fixtureDaysSince';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return null;
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return false;
    }
}
