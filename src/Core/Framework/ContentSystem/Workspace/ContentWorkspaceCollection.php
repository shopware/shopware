<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Workspace;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<ContentWorkspaceEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ContentWorkspaceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ContentWorkspaceEntity::class;
    }
}
