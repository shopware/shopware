<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Common;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\Validatable;
use Shopware\Core\Framework\Struct\Struct;

class ValidatableDefinition extends Struct implements Validatable
{
    /**
     * @var Rule|null
     */
    protected $rule;

    /**
     * @param null|Rule $rule
     */
    public function __construct($rule)
    {
        $this->rule = $rule;
    }

    public function getRule(): ? Rule
    {
        return $this->rule;
    }
}
