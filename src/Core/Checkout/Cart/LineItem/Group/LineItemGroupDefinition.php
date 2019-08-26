<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Content\Rule\RuleCollection;

class LineItemGroupDefinition
{
    /**
     * @var string
     */
    private $packagerKey;

    /**
     * @var float
     */
    private $value;

    /**
     * @var string
     */
    private $sorterKey;

    /**
     * @var RuleCollection
     */
    private $rules;

    public function __construct(string $packagerKey, float $value, string $sorterKey, RuleCollection $rules)
    {
        $this->packagerKey = $packagerKey;
        $this->value = $value;
        $this->sorterKey = $sorterKey;
        $this->rules = $rules;
    }

    public function getPackagerKey(): string
    {
        return $this->packagerKey;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getSorterKey(): string
    {
        return $this->sorterKey;
    }

    public function getRules(): RuleCollection
    {
        return $this->rules;
    }
}
