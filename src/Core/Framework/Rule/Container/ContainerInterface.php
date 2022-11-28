<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Rule\Rule;

/**
 * @package business-ops
 */
interface ContainerInterface
{
    /**
     * @param Rule[] $rules
     */
    public function setRules(array $rules): void;

    public function addRule(Rule $rule): void;
}
