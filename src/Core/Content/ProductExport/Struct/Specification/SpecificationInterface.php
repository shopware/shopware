<?php

namespace Shopware\Core\Content\ProductExport\Struct\Specification;

interface SpecificationInterface
{
    /**
     * Checks does specification is satisfied by provided value.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function isSatisfiedBy($value): bool;
}
