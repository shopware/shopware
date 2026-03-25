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
     * NULL enables identity mapping (passthrough with same names).
     *
     * @return array<string, ParameterBinding>|null
     */
    public function getParameterBindings(): ?array;
}
