<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewController;
use Shopware\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentRoute extends AbstractContentRoute
{
    /**
     * Field selection is not part of the content-route contract. The parameter list is repeated in
     * {@see ContentPreviewController}, which makes the same refusal on its own admission surface: every
     * surface admitting a content request refuses the parameter, whatever response class it builds. A shared
     * holder would have to be a service, and neither surface takes one for this.
     */
    private const FIELD_SELECTION_PARAMETERS = ['includes', 'excludes'];

    /**
     * @internal
     *
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly ContentSection $section,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly AbstractResponseFactory $responseFactory,
        private readonly ContentPipeline $contentPipeline,
        private readonly CacheFinalizer $cacheFinalizer,
    ) {
    }

    public function getDecorated(): AbstractContentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $path, Request $request, SalesChannelContext $context): AbstractContentRouteResponse
    {
        $this->rejectFieldSelection($request);

        $resolved = $this->specificationResolver->resolve($path, $request, $context);
        $specification = $resolved->specification;

        foreach ($this->section->buildRouteCacheTags($resolved->layoutId) as $tag) {
            $this->cacheTagCollector->addTag($tag);
        }

        $layoutEntity = $this->contentLayoutRepository
            ->search(new Criteria([$resolved->layoutId]), $context->getContext())
            ->getEntities()
            ->first();

        if (!$layoutEntity instanceof ContentLayoutEntity) {
            throw ContentSystemException::layoutNotFound($resolved->layoutId);
        }

        $cacheContext = new RenderingCacheContext();
        $cacheContext->addTags($specification->cacheTags);

        $result = $this->contentPipeline->load(
            RenderableLayout::fromEntity($layoutEntity),
            $specification,
            $cacheContext,
            $this->responseFactory->getRenderingMode(),
            $this->responseFactory->collectsValueIndex(),
            $context,
        );

        $this->cacheFinalizer->finalize($request, $cacheContext);

        return $this->responseFactory->createResponse($result);
    }

    /**
     * All three bags, because the framework's field-selection helper reads all three.
     */
    private function rejectFieldSelection(Request $request): void
    {
        foreach (self::FIELD_SELECTION_PARAMETERS as $parameter) {
            if ($request->attributes->has($parameter) || $request->query->has($parameter) || $request->request->has($parameter)) {
                throw ContentSystemException::fieldSelectionNotSupported($parameter);
            }
        }
    }
}
