<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ProductStream;

use Shopware\Core\Framework\ConditionTree\ConditionInterface;
use Shopware\Core\Framework\Struct\Struct;

abstract class ProductStream extends Struct implements ConditionInterface
{
    public const OPERATOR_GTE = '>=';

    public const OPERATOR_LTE = '<=';

    public const OPERATOR_EQ = '=';

    public const OPERATOR_NEQ = '!=';

    /**
     * Returns the api name for this product stream. The name has to be unique in the system.
     *
     * @return string
     */
    abstract public function getName(): string;

//    /**
//     * Validate the current product stream and returns a reason object which contains defines if the product stream match and if not why not
//     */
//    abstract public function match(ProductStreamScope $scope): Match;

    /**
     * Gets the constraints of the product stream
     * Format:
     *  [
     *   'propertyName' => [new Constraint(), new OtherConstraint()],
     *   'propertyName2' => [new Constraint(), new OtherConstraint()],
     *  ]
     */
    abstract public function getConstraints(): array;
}
