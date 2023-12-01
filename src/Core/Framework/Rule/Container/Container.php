<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;

/**
 * AbstractContainer implements setRules and addRule of the container interface
 */
#[Package('services-settings')]
abstract class Container extends Rule implements ContainerInterface
{
    /**
     * @var list<Rule>
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
     * @param list<Rule> $rules
     */
    public function __construct(array $rules = [])
    {
        parent::__construct();
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    /**
     * @param list<Rule> $rules
     */
    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }

    public function addRule(Rule $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @return list<Rule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getConstraints(): array
    {
        return [
            'rules' => [new ArrayOfType(Rule::class)],
        ];
    }
}
