<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraint;

#[Package('business-ops')]
abstract class Rule extends Struct
{
    public const RULE_NAME = null;

    public const OPERATOR_GTE = '>=';

    public const OPERATOR_LTE = '<=';

    public const OPERATOR_GT = '>';

    public const OPERATOR_LT = '<';

    public const OPERATOR_EQ = '=';

    public const OPERATOR_NEQ = '!=';

    public const OPERATOR_EMPTY = 'empty';

    protected string $_name;

    public function __construct()
    {
        $this->_name = $this->getName();
    }

    /**
     * Returns the api name for this rule. The name has to be unique in the system.
     */
    public function getName(): string
    {
        $ruleName = static::RULE_NAME;

        if ($ruleName === null) {
            throw new \Error('Implement own getName or add RULE_NAME constant');
        }

        return $ruleName;
    }

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
     *
     * @return array<string, array<Constraint>>
     */
    abstract public function getConstraints(): array;

    /**
     * Get the config which contains operators and fields to be rendered in the admin.
     */
    public function getConfig(): ?RuleConfig
    {
        return null;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions'], $data['_class']);
        $data['_name'] = $this->getName();

        // filter out null values to avoid constraint violations with empty operator
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }

    public function getApiAlias(): string
    {
        return 'rule_' . $this->getName();
    }
}
