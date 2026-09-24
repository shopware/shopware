<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutBranch;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
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
    name: 'shopware-content-layout-branch-create',
    title: 'Content Layout Branch Create',
    description: 'Start a new draft of a content layout: creates a draft branch, by default from the published layout. Every change made with shopware-content-layout-edit lands in a draft branch and never touches the live storefront; going live is a separate step (shopware-content-layout-publish). dryRun=true (default) only checks that the branch can be created; set dryRun=false to create it. Returns the branch with its id and head revision id.'
)]
#[McpToolGroup('content-layout')]
#[McpToolRequires('content_layout:update')]
class ContentLayoutBranchCreateTool extends McpToolResponse
{
    public function __construct(
        private readonly LayoutRevisionService $revisionService,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param string $layoutId The content layout id (from shopware-content-layout-list).
     * @param string $name Name of the draft shown in the Administration. Empty uses the default name.
     * @param string $fromRevisionId Revision to start the draft from. Empty starts from the published layout.
     * @param bool $dryRun true (default) previews without creating the branch; false creates it.
     */
    public function __invoke(string $layoutId, string $name = '', string $fromRevisionId = '', bool $dryRun = true): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'content_layout:update')) {
            return $error;
        }

        if ($dryRun) {
            return $this->executeWithDryRun($this->connection, $context, fn (): string => $this->create($layoutId, $name, $fromRevisionId, true));
        }

        return $this->create($layoutId, $name, $fromRevisionId, false);
    }

    private function create(string $layoutId, string $name, string $fromRevisionId, bool $dryRun): string
    {
        try {
            $branch = $this->revisionService->createBranch(
                $layoutId,
                $name === '' ? null : $name,
                $fromRevisionId === '' ? null : $fromRevisionId,
            );
        } catch (ShopwareHttpException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->success(
            ['layoutId' => $layoutId, 'branch' => $this->branch($branch, $dryRun)],
            ['dryRun' => $dryRun],
        );
    }

    /**
     * A dry run rolls the branch back, so its id is withheld: it names nothing an edit could address.
     *
     * @return array<string, string|null>
     */
    private function branch(LayoutBranch $branch, bool $dryRun): array
    {
        return [
            'id' => $dryRun ? null : $branch->id,
            'name' => $branch->name,
            'base' => $branch->base,
            'head' => $branch->head,
        ];
    }
}
