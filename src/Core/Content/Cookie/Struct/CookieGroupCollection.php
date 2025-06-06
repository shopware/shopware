<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<CookieGroup>
 */
#[Package('framework')]
class CookieGroupCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cookie_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return CookieGroup::class;
    }
}
