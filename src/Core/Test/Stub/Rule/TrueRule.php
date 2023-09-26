<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class TrueRule extends Rule
{
    final public const RULE_NAME = 'true';

    public function match(RuleScope $matchContext): bool
    {
        return true;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
