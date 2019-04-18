<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Struct\Struct;

abstract class Rule extends Struct
{
    public const OPERATOR_GTE = '>=';

    public const OPERATOR_LTE = '<=';

    public const OPERATOR_GT = '>';

    public const OPERATOR_LT = '<';

    public const OPERATOR_EQ = '=';

    public const OPERATOR_NEQ = '!=';

    /**
     * Returns the api name for this rule. The name has to be unique in the system.
     */
    abstract public function getName(): string;

    /**
     * Validate the current rule and returns the matching of the rule
     */
    abstract public function match(RuleScope $scope): bool;

    /**
     * Gets the constraints of the rule
     * Format:
     *  [
     *   'propertyName' => [new Constraint(), new OtherConstraint()],
     *   'propertyName2' => [new Constraint(), new OtherConstraint()],
     *  ]
     */
    abstract public function getConstraints(): array;
}
