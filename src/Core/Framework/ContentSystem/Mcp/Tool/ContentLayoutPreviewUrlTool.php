<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-content-layout-preview-url',
    title: 'Content Layout Preview URL',
    description: 'Get a storefront preview link for a draft of a content layout, so the user can look at the draft before it goes live. The link renders the head revision of the draft branch with real shop data, using the first product, category or landing page the layout is assigned to, and expires after 5 minutes. Nothing is saved or published. Read-only.'
)]
#[McpToolGroup('content-layout')]
#[McpToolRequires('content_layout:read')]
class ContentLayoutPreviewUrlTool extends McpToolResponse
{
    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly LayoutRevisionService $revisionService,
        private readonly ContentPreviewPageBuilder $previewPageBuilder,
        private readonly ContentPreviewPayloadStore $payloadStore,
        private readonly StoredTreeCodec $treeCodec,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly RequestStack $requestStack,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    /**
     * @param string $layoutId The content layout id (from shopware-content-layout-list).
     * @param string $branchId The draft branch to preview.
     */
    public function __invoke(string $layoutId, string $branchId): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'content_layout:read')) {
            return $error;
        }

        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return $this->error('A preview link can only be minted within an HTTP request.');
        }

        try {
            $head = $this->revisionService->getBranch($layoutId, $branchId)['head'];

            $target = $this->previewTarget($layoutId, $context);
            if ($target === null) {
                return $this->error('The layout is not assigned to any product, category or landing page, so there is no page to render the preview on. Assign the layout first.');
            }

            $salesChannelId = $target['salesChannelId'] ?? $this->storefrontSalesChannelId($context);
            if ($salesChannelId === null) {
                return $this->error('There is no storefront sales channel with a domain to render the preview in.');
            }

            $payload = new ContentPreviewRequest(
                layout: $this->treeCodec->encode(new StoredTree($head->tree)),
                entityType: $target['entityType'],
                entityId: $target['entityId'],
                salesChannelId: $salesChannelId,
            );

            $this->previewPageBuilder->build($payload, $context);
            $token = $this->payloadStore->store($payload);
        } catch (ShopwareHttpException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->success([
            'url' => \sprintf('%s%s/content-system/preview/%s', $request->getSchemeAndHttpHost(), rtrim($request->getBaseUrl(), '/'), $token),
            'head' => $head->id,
            'entityType' => $target['entityType'],
            'entityId' => $target['entityId'],
            'salesChannelId' => $salesChannelId,
        ]);
    }

    /**
     * @return array{entityType: string, entityId: string, salesChannelId: string|null}|null
     */
    private function previewTarget(string $layoutId, Context $context): ?array
    {
        $criteria = new Criteria([$layoutId]);
        foreach (['productContentLayouts', 'categoryContentLayouts', 'landingPageContentLayouts'] as $association) {
            $criteria->getAssociation($association)->setLimit(1);
        }

        $layout = $this->contentLayoutRepository->search($criteria, $context)->getEntities()->first();

        return $layout === null ? null : $this->firstAssignment($layout);
    }

    /**
     * @return array{entityType: string, entityId: string, salesChannelId: string|null}|null
     */
    private function firstAssignment(ContentLayoutEntity $layout): ?array
    {
        $product = $layout->getProductContentLayouts()?->first();
        if ($product !== null) {
            return ['entityType' => ProductContentLayoutDefinition::CONTENT_LAYOUT_ENTITY_TYPE, 'entityId' => $product->getProductId(), 'salesChannelId' => $product->getSalesChannelId()];
        }

        $category = $layout->getCategoryContentLayouts()?->first();
        if ($category !== null) {
            return ['entityType' => CategoryContentLayoutDefinition::CONTENT_LAYOUT_ENTITY_TYPE, 'entityId' => $category->getCategoryId(), 'salesChannelId' => $category->getSalesChannelId()];
        }

        $landingPage = $layout->getLandingPageContentLayouts()?->first();
        if ($landingPage !== null) {
            return ['entityType' => LandingPageContentLayoutDefinition::CONTENT_LAYOUT_ENTITY_TYPE, 'entityId' => $landingPage->getLandingPageId(), 'salesChannelId' => $landingPage->getSalesChannelId()];
        }

        return null;
    }

    private function storefrontSalesChannelId(Context $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('domains.id', null)]))
            ->setLimit(1);

        return $this->salesChannelRepository->searchIds($criteria, $context)->firstId();
    }
}
