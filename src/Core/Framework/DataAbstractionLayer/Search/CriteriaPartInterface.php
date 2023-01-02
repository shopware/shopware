<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
#[Package('core')]
interface CriteriaPartInterface
{
    public function getFields(): array;
}
