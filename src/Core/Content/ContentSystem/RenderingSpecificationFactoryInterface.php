<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Strategy interface for creating RenderingSpecification from different sources.
 *
 * Implementations translate different input sources (URL routing, entity IDs, etc.)
 * into a unified RenderingSpecification for the rendering pipeline.
 *
 * @internal
 */
#[Package('discovery')]
interface RenderingSpecificationFactoryInterface
{
    /**
     * Return null if cannot handle path - enables Chain of Responsibility pattern
     * where multiple factories are tried in priority order.
     *
     * @throws ContentSystemException When creation fails for a path this factory should handle
     */
    public function create(string $path, Request $request, SalesChannelContext $context): ?RenderingSpecification;
}
