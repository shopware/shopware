<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class VersionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return VersionEntity::class;
    }
}
