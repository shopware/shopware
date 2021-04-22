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

    public const OPERATOR_EMPTY = 'empty';

    /**
     * @var string
     */
    protected $_name;

    public function __construct()
    {
        $this->_name = $this->getName();
    }

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

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions'], $data['_class']);
        $data['_name'] = $this->getName();

        return $data;
    }

    public function getApiAlias(): string
    {
        return 'rule_' . $this->getName();
    }
}
