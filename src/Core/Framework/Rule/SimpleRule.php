<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

/**
 * @package business-ops
 */
class SimpleRule extends Rule
{
    protected bool $match = false;

    /**
     * @internal
     */
    public function __construct(bool $match = true)
    {
        parent::__construct();

        $this->match = $match;
    }

    public function getName(): string
    {
        return 'simple';
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
