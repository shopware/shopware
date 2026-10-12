<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 *
 * Simple test entity used as items in TestChildEntity.
 */
class TestItemEntity extends Entity
{
    public string $id;

    public ?string $name = null;
}
