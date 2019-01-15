<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ConditionTree;

class ConditionRegistry
{
    /**
     * @var ConditionInterface[]
     */
    private $conditions;

    public function __construct(iterable $taggedConditions)
    {
        $this->mapConditions($taggedConditions);
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->conditions);
    }

    public function has(string $name): bool
    {
        try {
            $this->getInstance($name);
        } catch (InvalidConditionException $exception) {
            return false;
        }

        return true;
    }

    public function getInstance(string $name): ConditionInterface
    {
        if (!array_key_exists($name, $this->conditions)) {
            throw new InvalidConditionException($name);
        }

        return $this->conditions[$name];
    }

    public function getConditionClass(string $name): string
    {
        return get_class($this->getInstance($name));
    }

    private function mapConditions(iterable $taggedConditions): void
    {
        $this->conditions = [];

        /** @var ConditionInterface $condition */
        foreach ($taggedConditions as $condition) {
            $this->conditions[$condition->getName()] = $condition;
        }
    }
}
