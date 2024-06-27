<?php

namespace Shopware\Core\Framework\DataAbstractionLayer;

interface AttributeConstraintAwareInterface
{
    public function getConstraints(): array;
}
