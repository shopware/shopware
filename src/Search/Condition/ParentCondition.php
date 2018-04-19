<?php

namespace Shopware\Search\Condition;

use Shopware\Search\ConditionInterface;

class ParentCondition implements ConditionInterface
{
    /**
     * @var int[]
     */
    protected $parentIds;

    public function __construct(array $parentIds)
    {
        $this->parentIds = $parentIds;
    }

    public function getParentIds(): array
    {
        return $this->parentIds;
    }

    public function getName(): string
    {
        return self::class;
    }
}