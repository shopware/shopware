<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\HeaderContentLayout;

use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Uses domain-aware resolution instead of entity-based path matching.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class HeaderSpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<HeaderContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly DomainAwareLayoutResolver $resolver,
        private readonly EntityRepository $repository,
    ) {
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return true;
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        return $this->resolveAssignment($context)->getContentLayoutId();
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        $scalarParameters = array_filter($request->query->all(), '\is_scalar');

        return new SpecificationData(
            dataRequirements: [],
            placeholderValues: PlaceholderValues::from($scalarParameters),
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    {
        $layoutId = $this->resolveAssignment($context)->getContentLayoutId();

        return [ContentSection::HEADER->buildLayoutTag($layoutId)];
    }

    private function resolveAssignment(SalesChannelContext $context): AbstractContentLayoutAssignmentEntity
    {
        $assignment = $this->resolver->resolve($context, $this->repository);

        if ($assignment === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                'header',
                '',
                $context->getSalesChannel()->getId()
            );
        }

        return $assignment;
    }
}
