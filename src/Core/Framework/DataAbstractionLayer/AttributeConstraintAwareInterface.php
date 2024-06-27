<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
interface AttributeConstraintAwareInterface
{
    public function getConstraints(): array;
}
