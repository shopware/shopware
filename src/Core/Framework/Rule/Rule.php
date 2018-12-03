<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Struct\Struct;

abstract class Rule extends Struct
{
    public const OPERATOR_GTE = '>=';

    public const OPERATOR_LTE = '<=';

    public const OPERATOR_EQ = '=';

    public const OPERATOR_NEQ = '!=';

    /**
     * @var string
     */
    protected $type;

    public function __construct()
    {
        $this->type = static::class;
    }

    /**
     * Validate the current rule and returns a reason object which contains defines if the rule match and if not why not
     */
    abstract public function match(RuleScope $scope): Match;

    /**
     * Gets the constraints of the rule
     * Format:
     *  [
     *   'propertyName' => [new Constraint(), new OtherConstraint()],
     *   'propertyName2' => [new Constraint(), new OtherConstraint()],
     *  ]
     */
    abstract public static function getConstraints(): array;
}
