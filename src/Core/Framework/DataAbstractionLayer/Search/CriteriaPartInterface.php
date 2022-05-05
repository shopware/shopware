<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
interface CriteriaPartInterface
{
    public function getFields(): array;
}
