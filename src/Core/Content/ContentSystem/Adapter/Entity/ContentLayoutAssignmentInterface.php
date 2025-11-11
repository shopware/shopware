<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity;

use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Framework\Log\Package;

/**
 * Contract for entities that represent content layout assignments.
 *
 * Entities implementing this interface can be adapted to the ContentSystem
 * rendering pipeline, enabling entity-based rendering (bypassing URL routing).
 *
 * @internal
 */
#[Package('discovery')]
interface ContentLayoutAssignmentInterface
{
    /**
     * Returns the ID of the assigned content layout.
     *
     * Required for EntityLayoutBuilder to determine which layout to render for this entity.
     */
    public function getContentLayoutId(): string;

    /**
     * Returns parameter bindings for placeholder mapping and entity resolution.
     *
     * NULL = identity mapping (passthrough with same names).
     *
     * @return array<string, ParameterBinding>|null
     */
    public function getParameterBindings(): ?array;

    /**
     * Returns the ID of the entity this layout is assigned to.
     *
     * This generic getter enables polymorphic handling of different entity types
     * without knowing the specific getter method name (getProductId, getCategoryId, etc.).
     */
    public function getAssignedEntityId(): string;
}
