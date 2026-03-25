<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Abstract base for creating RenderingSpecification from different sources.
 *
 * Implementations translate different input sources (URL routing, entity IDs, etc.)
 * into a unified RenderingSpecification for the rendering pipeline.
 */
#[Package('discovery')]
abstract class AbstractRenderingSpecificationFactory
{
    abstract public function getDecorated(): AbstractRenderingSpecificationFactory;

    /**
     * Return null if cannot handle path - enables Chain of Responsibility pattern
     * where multiple factories are tried in priority order.
     *
     * @throws ContentSystemException When creation fails for a path this factory should handle
     */
    abstract public function create(string $path, Request $request, SalesChannelContext $context): ?RenderingSpecification;
}
