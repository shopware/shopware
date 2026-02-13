<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Helper;

use Shopware\Core\Framework\Log\Package;

use function Symfony\Component\String\u;

/**
 * Derives ContentSystem metadata from entity type using conventional patterns.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ContentLayoutMetadataDeriver
{
    /**
     * Derives entity ID field name from entity type.
     *
     * @return non-empty-string
     */
    public function deriveEntityIdField(string $entityType): string
    {
        return u($entityType)->camel()->toString() . 'Id';
    }

    /**
     * Derives URL path prefix from entity type.
     */
    public function derivePathPrefix(string $entityType): string
    {
        return '/' . u($entityType)->kebab()->toString() . '/';
    }

    /**
     * Creates route placeholder pattern for entity ID field.
     */
    public function deriveRoutePattern(string $entityIdField): string
    {
        return '{' . $entityIdField . '}';
    }
}
