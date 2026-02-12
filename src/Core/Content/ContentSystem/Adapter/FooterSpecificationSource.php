<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\FooterContentLayout\FooterContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Content\ContentSystem\ContentSection;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\SpecificationData;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Uses domain-aware resolution instead of entity-based path matching.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class FooterSpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<FooterContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly DomainAwareLayoutResolver $resolver,
        private readonly EntityRepository $repository,
        private readonly RequestDataExtractor $requestDataExtractor,
    ) {
    }

    public function getDecorated(): AbstractSpecificationSource
    {
        throw new DecorationPatternException(self::class);
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
        $assignment = $this->resolveAssignment($context);
        $processedParameters = $this->requestDataExtractor->extractData($request, $assignment->getParameterBindings());

        return new SpecificationData(
            dataRequirements: [],
            placeholderValues: PlaceholderValues::from($processedParameters),
        );
    }

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

        return [ContentSection::FOOTER->buildLayoutTag($layoutId)];
    }

    private function resolveAssignment(SalesChannelContext $context): ContentLayoutAssignmentInterface
    {
        $assignment = $this->resolver->resolve($context, $this->repository);

        if ($assignment === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                'footer',
                '',
                $context->getSalesChannel()->getId()
            );
        }

        return $assignment;
    }
}
