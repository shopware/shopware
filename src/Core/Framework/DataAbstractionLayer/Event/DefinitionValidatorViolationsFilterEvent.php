<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class DefinitionValidatorViolationsFilterEvent
{
    /**
     * @param array<string,array<string>> $violations
     */
    public function __construct(
        public array $violations,
    )
    {
    }

    public function filterByDefinitionClass(callable $filter): void
    {
        $this->violations = array_filter($this->violations, $filter,ARRAY_FILTER_USE_KEY);
    }
}
