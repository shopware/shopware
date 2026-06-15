<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class RenderingSpecificationResolver
{
    /**
     * @param iterable<AbstractSpecificationSource> $sources
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly RenderingSpecificationFactory $factory,
    ) {
    }

    /**
     * @throws ContentSystemException When no source can handle the path
     */
    public function resolve(
        string $path,
        Request $request,
        SalesChannelContext $context
    ): RenderingSpecification {
        foreach ($this->sources as $source) {
            if ($source->supports($path, $request, $context)) {
                return $this->factory->create($source, $path, $request, $context);
            }
        }

        throw ContentSystemException::noFactoryCanHandle($path);
    }
}
