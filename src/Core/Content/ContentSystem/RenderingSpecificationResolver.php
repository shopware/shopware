<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns the RenderingSpecification for content route requests.
 *
 * Delegates to registered factories in priority order and returns the first
 * specification produced. Factories return null if they cannot handle the path.
 *
 * @internal
 */
#[Package('discovery')]
final class RenderingSpecificationResolver
{
    /**
     * @param iterable<AbstractRenderingSpecificationFactory> $factories
     */
    public function __construct(
        private readonly iterable $factories
    ) {
    }

    /**
     * @throws ContentSystemException When no factory can handle the path
     */
    public function resolve(
        string $path,
        Request $request,
        SalesChannelContext $context
    ): RenderingSpecification {
        foreach ($this->factories as $factory) {
            $specification = $factory->create($path, $request, $context);

            if ($specification !== null) {
                return $specification;
            }
        }

        throw ContentSystemException::noFactoryCanHandle($path);
    }
}
