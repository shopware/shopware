<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
#[\Attribute(\Attribute::TARGET_CLASS)]
class Entity
{
    /**
     * @var class-string
     */
    public string $class;

    /**
     * @param class-string<EntityCollection> $collectionClass
     *
     * @phpstan-ignore missingType.generics (At this point it is not really possible to determine the correct entity class)
     */
    public function __construct(
        public string $name,
        public ?string $parent = null,
        public ?string $since = null,
        public string $collectionClass = EntityCollection::class,
    ) {
    }
}
