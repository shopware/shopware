<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 *
 * Test entity with two different relationships (childA and childB) that can point
 * to the same TestChildEntity. This allows testing that the same entity accessed
 * through different paths gets its associations merged.
 */
class TestParentEntity extends Entity
{
    public string $id;

    public ?string $childAId = null;

    public ?string $childBId = null;

    public ?TestChildEntity $childA = null;

    public ?TestChildEntity $childB = null;
}
