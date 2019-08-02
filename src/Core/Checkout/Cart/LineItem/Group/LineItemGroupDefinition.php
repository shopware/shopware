<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Content\Rule\RuleCollection;

class LineItemGroupDefinition
{
    /**
     * @var string
     */
    private $id;

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

    public function __construct(string $id, string $packagerKey, float $value, string $sorterKey, RuleCollection $rules)
    {
        $this->id = $id;
        $this->packagerKey = $packagerKey;
        $this->value = $value;
        $this->sorterKey = $sorterKey;
        $this->rules = $rules;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Gets the packager key of the group definition.
     */
    public function getPackagerKey(): string
    {
        return $this->packagerKey;
    }

    /**
     * Gets the value for the group definition that
     * is being used for packaging items.
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * Gets the sorter key of the group definition.
     */
    public function getSorterKey(): string
    {
        return $this->sorterKey;
    }

    /**
     * Gets the assigned rules that are being used
     * to package items by using this group definition.
     */
    public function getRules(): RuleCollection
    {
        return $this->rules;
    }
}
