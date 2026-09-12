<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppSeoUrlRouteEntity>
 */
#[Package('framework')]
class AppSeoUrlRouteCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppSeoUrlRouteEntity::class;
    }
}
