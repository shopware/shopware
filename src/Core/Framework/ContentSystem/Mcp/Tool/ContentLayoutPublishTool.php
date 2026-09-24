<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Api\LayoutDiagnosticsResultNormalizer;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
    name: 'shopware-content-layout-publish',
    title: 'Content Layout Publish',
    description: 'Publish a draft of a content layout to the live storefront: makes the head revision of a draft branch (branchId) or one specific revision (revisionId) the live layout. The branch is kept. Only publish when the user explicitly asks to go live. dryRun=true (default) only checks whether the revision can be served (resolvable) and lists the violations that would block publishing, without changing anything; set dryRun=false to publish.'
)]
#[McpToolGroup('content-layout')]
#[McpToolRequires('content_layout:update')]
class ContentLayoutPublishTool extends McpToolResponse
{
    private const MAX_CANDIDATES = 3;

    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly LayoutRevisionService $revisionService,
        private readonly LayoutDiagnostics $layoutDiagnostics,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    /**
     * @param string $layoutId The content layout id (from shopware-content-layout-list).
     * @param string $branchId Publish the head revision of this draft branch. Leave empty when passing revisionId.
     * @param string $revisionId Publish this revision. Leave empty when passing branchId.
     * @param bool $dryRun true (default) only checks the revision; false publishes it to the live storefront.
     */
    public function __invoke(string $layoutId, string $branchId = '', string $revisionId = '', bool $dryRun = true): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'content_layout:update')) {
            return $error;
        }

        if (($branchId === '') === ($revisionId === '')) {
            return $this->error('Pass either branchId or revisionId.');
        }

        try {
            $graph = $this->revisionService->listRevisions($layoutId);

            if ($branchId !== '') {
                $revisionId = ($graph->branch($branchId) ?? throw ContentSystemException::contentLayoutBranchNotFound($layoutId, $branchId))->head;
            }

            $revision = $graph->revision($revisionId) ?? throw ContentSystemException::contentLayoutRevisionNotFound($layoutId, $revisionId);

            if ($dryRun) {
                $rootSource = $this->contentLayoutRepository->search(new Criteria([$layoutId]), $context)->getEntities()->first()?->getRootSource();
                $analysis = $this->layoutDiagnostics->analyze($revision->tree, $this->rootSourceRegistry->resolveGated($rootSource, $context));

                return $this->success([
                    'layoutId' => $layoutId,
                    'revisionId' => $revision->id,
                    'alreadyPublished' => $revision->id === $graph->published,
                    'diagnostics' => $this->diagnostics($analysis->report),
                ], ['dryRun' => true]);
            }

            $this->revisionService->publish($layoutId, $revision->id, null, $context);
        } catch (ShopwareHttpException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->success(['layoutId' => $layoutId, 'published' => $revision->id], ['dryRun' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnostics(DiagnosticsReport $report): array
    {
        return (new LayoutDiagnosticsResultNormalizer())->normalizeReport(new DiagnosticsReport(array_map(
            static fn (Violation $violation): Violation => new Violation(
                $violation->code,
                $violation->elementId,
                $violation->key,
                $violation->message,
                \array_slice($violation->candidates, 0, self::MAX_CANDIDATES),
            ),
            $report->violations,
        )));
    }
}
