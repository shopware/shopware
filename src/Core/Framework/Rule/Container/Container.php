<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Rule\Rule;

/**
 * AbstractContainer implements setRule and addRule of the container interface
 */
abstract class Container extends Rule
{
    /**
     * @var Rule[]
     */
    protected $rules = [];

    /**
     * Constructor params will be used for internal rules
     *
     * new ConcreteContainer(
     *      new TrueRule,
     *      new FalseRule,
     * )
     *
     * @param Rule[] $rules
     */
    public function __construct(array $rules = [])
    {
        parent::__construct();
        array_map([$this, 'addRule'], $rules);
    }

    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }

    public function addRule(Rule $rule): void
    {
        $this->rules[] = $rule;
    }

    public function getRules(): array
    {
        return $this->rules;
    }
}
