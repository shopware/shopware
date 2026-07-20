<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for internal use as it only loads the default category levels
 * externals should rely on the @see NavigationLoader
 */
#[Package('discovery')]
interface DefaultCategoryLevelLoaderInterface
{
    public function loadLevels(
        string $rootId,
        int $rootLevel,
        SalesChannelContext $context,
        Criteria $criteria,
        int $depth,
    ): CategoryCollection;
}
