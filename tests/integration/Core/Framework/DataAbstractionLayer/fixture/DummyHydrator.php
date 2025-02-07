<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @internal
 */
class DummyHydrator extends EntityHydrator
{
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.number'])) {
            $entity->number = 'code-' . $row[$root . '.number'];
        }

        return $entity;
    }
}
