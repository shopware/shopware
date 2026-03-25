<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Content\ContentSystem\LayoutType;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creates RenderingSpecification for header content layouts.
 *
 * Bypasses the factory chain used by main content routes,
 * directly using DomainAwareLayoutResolver for header-specific resolution.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class HeaderSpecificationFactory
{
    /**
     * @param EntityRepository<HeaderContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly DomainAwareLayoutResolver $resolver,
        private readonly EntityRepository $repository,
        private readonly RequestDataExtractor $requestDataExtractor,
    ) {
    }

    public function create(Request $request, SalesChannelContext $context): RenderingSpecification
    {
        $assignment = $this->resolver->resolve($context, $this->repository);

        if ($assignment === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                'header',
                '',
                $context->getSalesChannel()->getId()
            );
        }

        $bindings = $assignment->getParameterBindings();
        $processedParameters = $this->requestDataExtractor->extractData($request, $bindings);
        $placeholderValues = PlaceholderValues::from($processedParameters);

        return new RenderingSpecification(
            layoutId: $assignment->getContentLayoutId(),
            dataRequirements: [],
            placeholderValues: $placeholderValues,
            request: $request,
            layoutType: LayoutType::HEADER,
            targetElementId: null,
            cacheTags: ['header-content-layout-' . $assignment->getContentLayoutId()],
        );
    }
}
