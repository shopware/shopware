<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpResource;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpResourceEntity>
 */
#[Package('framework')]
class AppMcpResourceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpResourceEntity::class;
    }
}
