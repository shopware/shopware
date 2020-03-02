<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class AlwaysValidRule extends Rule
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'alwaysValid';
    }

    /**
     * {@inheritdoc}
     */
    public function match(RuleScope $scope): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints(): array
    {
        return [];
    }
}
