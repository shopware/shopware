<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ConditionTree;

interface ContainerInterface
{
    /**
     * @param ConditionInterface[] $children
     */
    public function setChildren(array $children): void;

    public function addChild(ConditionInterface $child): void;
}
