<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Common;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class TrueRule extends Rule
{
    public function match(RuleScope $matchContext): bool
    {
        return true;
    }

    public function getConstraints(): array
    {
        return [];
    }

    public function getName(): string
    {
        return 'true';
    }
}
