<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<InvalidPluralizationStruct>
 */
#[Package('discovery')]
class InvalidPluralizationCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return InvalidPluralizationStruct::class;
    }
}
