<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ConditionTree;

interface ConditionInterface
{
    public function getName(): string;

    public function getConstraints(): array;
}
