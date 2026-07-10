<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * The two built-in resolvedBy loader branches a `resolvedBy` bare property-reference string resolves
 * against: a reference property whose declared FQCN subclasses {@see Entity} resolves through the `entity`
 * loader and stores a single ID string, one subclassing {@see EntityCollection} resolves through the
 * `entity_collection` loader and stores a list of ID strings. Closed by design: no third branch, no
 * loader-registry search.
 *
 * @internal
 */
#[Package('framework')]
enum ResolvedByLoaderBranch
{
    case Entity;
    case EntityCollection;

    /**
     * The branch a reference property's declared FQCN belongs to, or null when the FQCN subclasses neither
     * base class.
     */
    public static function fromReferenceFqcn(string $fqcn): ?self
    {
        if (is_subclass_of($fqcn, Entity::class)) {
            return self::Entity;
        }

        if (is_subclass_of($fqcn, EntityCollection::class)) {
            return self::EntityCollection;
        }

        return null;
    }

    public function loaderSource(): string
    {
        return match ($this) {
            self::Entity => EntityLoader::SOURCE,
            self::EntityCollection => EntityCollectionLoader::SOURCE,
        };
    }

    /**
     * The inverse of {@see self::loaderSource()}: the branch a `resolves` entry's loader source belongs to, or
     * null when the source is neither built-in resolvedBy loader.
     */
    public static function fromLoaderSource(string $source): ?self
    {
        return match ($source) {
            EntityLoader::SOURCE => self::Entity,
            EntityCollectionLoader::SOURCE => self::EntityCollection,
            default => null,
        };
    }

    /**
     * Whether a stored value matches this branch's shape: a single ID string for `Entity`, a list whose every
     * entry is a string for `EntityCollection`. Deliberately stricter than the serve path, which filters
     * non-string entries tolerantly.
     */
    public function matchesStoredValueShape(mixed $value): bool
    {
        return match ($this) {
            self::Entity => \is_string($value),
            self::EntityCollection => \is_array($value) && array_is_list($value) && $this->everyEntryIsString($value),
        };
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function everyEntryIsString(array $value): bool
    {
        foreach ($value as $entry) {
            if (!\is_string($entry)) {
                return false;
            }
        }

        return true;
    }
}
