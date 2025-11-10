<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Contract for EntityDefinitions of entities that can be adapted to ContentSystem.
 *
 * Provides ContentSystem-specific metadata needed to adapt entity-based paths
 * to the rendering pipeline (URL patterns, entity identification, etc.).
 *
 * @internal
 */
#[Package('discovery')]
interface ContentLayoutAssignableDefinitionInterface
{
    /**
     * Returns the field name used to identify the assigned entity in the assignment table.
     */
    public function getContentLayoutEntityIdField(): string;

    /**
     * Returns the entity type name for exception messages and logging.
     */
    public function getContentLayoutEntityType(): string;

    /**
     * Returns the URL path prefix for this entity type.
     *
     * Used by Chain of Responsibility pattern to route requests.
     */
    public function getContentLayoutPathPrefix(): string;

    /**
     * Returns the route pattern with placeholder for entity ID extraction.
     *
     * Used with Symfony's UrlMatcher to extract entity ID from path.
     */
    public function getContentLayoutRoutePattern(): string;

    /**
     * Returns page-level data requirements for this entity type.
     *
     * These requirements are loaded once per page and distributed to all
     * root elements via virtual root pattern during hydration.
     *
     * Entity definitions can return context-aware requirements based on
     * sales channel, customer group, or other contextual information.
     *
     * @return array<\Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement>
     */
    public function getPageDataRequirements(SalesChannelContext $context): array;
}
