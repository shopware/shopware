<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;

#[Package('business-ops
AbstractContainer implements setRules and addRule of the container interface')]
abstract class Container extends Rule implements ContainerInterface
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
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    /**
     * @param Rule[] $rules
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
     * @return Rule[]
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
