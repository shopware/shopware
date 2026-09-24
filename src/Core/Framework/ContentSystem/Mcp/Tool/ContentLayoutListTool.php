<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-content-layout-list',
    title: 'Content Layout List',
    description: 'Find Experience Studio content layouts (the page layouts of products, categories, landing pages, header and footer) and their layoutId, e.g. to locate the layout named "Basic Listing". Returns per layout: id, name, rootSource (product, category, landing_page, header, footer or none), the number of draft branches, and how many products, categories and landing pages use it. Optional name filter (substring match). Read-only.'
)]
#[McpToolGroup('content-layout')]
#[McpToolRequires('content_layout:read')]
class ContentLayoutListTool extends McpToolResponse
{
    private const LIMIT = 100;

    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly EntityRepository $contentLayoutRepository,
        private readonly LayoutRevisionService $revisionService,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    /**
     * @param string $name Only layouts whose name contains this text. Empty lists all layouts.
     */
    public function __invoke(string $name = ''): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'content_layout:read')) {
            return $error;
        }

        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('name'))
            ->setLimit(self::LIMIT)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->addAssociations(['productContentLayouts', 'categoryContentLayouts', 'landingPageContentLayouts']);

        if ($name !== '') {
            $criteria->addFilter(new ContainsFilter('name', $name));
        }

        $result = $this->contentLayoutRepository->search($criteria, $context);

        $layouts = [];
        foreach ($result->getEntities() as $layout) {
            $layouts[] = [
                'id' => $layout->getId(),
                'name' => $layout->getName(),
                'rootSource' => $layout->getRootSource(),
                'branchCount' => \count($this->revisionService->listBranches($layout->getId())),
                'assignments' => array_filter([
                    ProductContentLayoutDefinition::CONTENT_LAYOUT_ENTITY_TYPE => $layout->getProductContentLayouts()?->count() ?? 0,
                    CategoryContentLayoutDefinition::CONTENT_LAYOUT_ENTITY_TYPE => $layout->getCategoryContentLayouts()?->count() ?? 0,
                    LandingPageContentLayoutDefinition::CONTENT_LAYOUT_ENTITY_TYPE => $layout->getLandingPageContentLayouts()?->count() ?? 0,
                ]),
            ];
        }

        return $this->success($layouts, ['total' => $result->getTotal(), 'limit' => self::LIMIT]);
    }
}
