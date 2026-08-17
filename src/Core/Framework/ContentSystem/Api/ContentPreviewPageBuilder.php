<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\DraftLayoutChecker;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class ContentPreviewPageBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly DraftLayoutDecoder $decoder,
        private readonly ContentElementLowering $lowering,
        private readonly DraftLayoutChecker $layoutValidator,
        private readonly ContentPipeline $contentPipeline,
    ) {
    }

    /**
     * @return array{contentPage: ContentPage, salesChannelContext: SalesChannelContext}
     */
    public function build(ContentPreviewRequest $payload, Context $context): array
    {
        $salesChannelContext = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $payload->salesChannelId,
                Random::getAlphanumericString(32),
                $payload->languageId,
                $payload->currencyId,
                $payload->domainId,
                $context,
                $payload->customerId,
            )
        );

        $request = new Request($payload->queryParameters);

        $specification = $this->specificationResolver->resolveWithoutLayout(
            $payload->entityType,
            $payload->entityId,
            $request,
            $salesChannelContext,
        );

        // The draft decodes into the storage model, which is what the check reads. Only the render path still
        // speaks the older element model, so the lowering happens on that side alone.
        $stored = $this->decoder->decode($payload->layout);

        $violations = $this->layoutValidator->check($stored);
        if ($violations->count() > 0) {
            throw ContentSystemException::elementTypesInvalid($violations);
        }

        $renderableLayout = RenderableLayout::create(
            LayoutReference::create(Uuid::randomHex(), 'preview', null),
            $this->lowering->lowerTree($stored),
        );

        $contentPage = $this->contentPipeline->load(
            $renderableLayout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::FULL,
            $salesChannelContext,
        );

        return [
            'contentPage' => $contentPage,
            'salesChannelContext' => $salesChannelContext,
        ];
    }
}
