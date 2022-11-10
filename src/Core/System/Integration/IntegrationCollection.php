<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package core
 * @extends EntityCollection<IntegrationEntity>
 */
class IntegrationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'integration_collection';
    }

    protected function getExpectedClass(): string
    {
        return IntegrationEntity::class;
    }
}
