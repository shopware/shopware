<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Symfony\Component\VarExporter\Hydrator;

/**
 * AbstractContainer implements setRules and addRule of the container interface
 */
#[Package('fundamentals@after-sales')]
abstract class Container extends Rule implements ContainerInterface
{
    /**
     * @var list<Rule>
     */
    protected array $rules = [];

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
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        Hydrator::hydrate($this, $data);

        foreach ($this->rules as $i => $rule) {
            if ($rule instanceof \__PHP_Incomplete_Class) {
                $this->rules[$i] = new UnknownConditionRule();
            }
        }

        // @deprecated tag:v6.8.0 - Remove the compatibility call to subclass __wakeup hooks.
        if (!Feature::isActive('v6.8.0.0') && method_exists($this, '__wakeup')) {
            $this->__wakeup();
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
