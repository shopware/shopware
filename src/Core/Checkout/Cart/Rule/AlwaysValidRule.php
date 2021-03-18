<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class AlwaysValidRule extends Rule
{
    public function getName(): string
    {
        return 'alwaysValid';
    }

    public function match(RuleScope $scope): bool
    {
        return true;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
