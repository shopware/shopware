<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<VersionEntity>
 */
class VersionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_version_collection';
    }

    protected function getExpectedClass(): string
    {
        return VersionEntity::class;
    }
}
