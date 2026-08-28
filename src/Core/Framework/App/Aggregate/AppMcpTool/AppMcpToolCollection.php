<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppMcpTool;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpToolEntity>
 */
#[Package('framework')]
class AppMcpToolCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpToolEntity::class;
    }
}
