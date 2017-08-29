<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Validation\ConstraintBuilder;

interface ConstraintBuilderAware
{
    public function setConstraintBuilder(ConstraintBuilder $constraintBuilder): void;
}