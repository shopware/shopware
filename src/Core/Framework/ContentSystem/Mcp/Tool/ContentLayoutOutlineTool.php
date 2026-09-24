<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutBranch;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mcp\LayoutOutlineBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-content-layout-outline',
    title: 'Content Layout Outline',
    description: 'Show the element tree of a content layout as an indented outline, one line per element: "element id · component · slot · label/text preview". Use it first to get the element ids and slot names that shopware-content-layout-edit needs (e.g. to insert something below the product listing you need the listing\'s parent id and slot). Leave branchId empty for the published (live) layout, or pass a draft branchId to see that draft. Also returns the head revision id and the list of draft branches. Read-only.'
)]
#[McpToolGroup('content-layout')]
#[McpToolRequires('content_layout:read')]
class ContentLayoutOutlineTool extends McpToolResponse
{
    public function __construct(
        private readonly LayoutRevisionService $revisionService,
        private readonly LayoutOutlineBuilder $outlineBuilder,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    /**
     * @param string $layoutId The content layout id (from shopware-content-layout-list).
     * @param string $branchId A draft branch id. Empty shows the published layout.
     */
    public function __invoke(string $layoutId, string $branchId = ''): string
    {
        if ($error = $this->requirePrivilege($this->contextProvider->getContext(), 'content_layout:read')) {
            return $error;
        }

        try {
            $graph = $this->revisionService->listRevisions($layoutId);
            $headId = $graph->published;

            if ($branchId !== '') {
                $headId = ($graph->branch($branchId) ?? throw ContentSystemException::contentLayoutBranchNotFound($layoutId, $branchId))->head;
            }

            $head = $graph->revision($headId) ?? throw ContentSystemException::contentLayoutRevisionNotFound($layoutId, $headId);
            $branches = $this->revisionService->listBranches($layoutId);
        } catch (ShopwareHttpException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->success([
            'layoutId' => $layoutId,
            'branchId' => $branchId === '' ? null : $branchId,
            'head' => $head->id,
            'published' => $graph->published,
            'outline' => $this->outlineBuilder->build(new StoredTree($head->tree)),
            'branches' => array_map(
                static fn (LayoutBranch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'head' => $branch->head,
                    'updatedAt' => $branch->updatedAt->format(\DATE_ATOM),
                ],
                $branches,
            ),
        ]);
    }
}
