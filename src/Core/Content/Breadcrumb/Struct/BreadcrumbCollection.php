<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Breadcrumb>
 */
#[Package('inventory')]
class BreadcrumbCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'breadcrumb_collection';
    }

    protected function getExpectedClass(): string
    {
        return Breadcrumb::class;
    }
}
