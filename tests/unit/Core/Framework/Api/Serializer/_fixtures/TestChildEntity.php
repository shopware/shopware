<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 *
 * Test entity that can be referenced through multiple paths.
 * Has an `items` relationship that can have different data loaded depending on the access path.
 * Has a `parent` relationship for testing circular references (like OrderLineItem -> Order -> OrderLineItems).
 */
class TestChildEntity extends Entity
{
    public string $id;

    public ?string $name = null;

    public ?string $parentId = null;

    /**
     * @var array<string, TestItemEntity>|null
     */
    public ?array $items = null;

    /**
     * Reference to parent entity (for circular reference testing)
     */
    public ?TestParentEntity $parent = null;
}
