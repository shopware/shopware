<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<DomainRuleStruct>
 */
#[Package('framework')]
class DomainRuleCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return DomainRuleStruct::class;
    }
}
