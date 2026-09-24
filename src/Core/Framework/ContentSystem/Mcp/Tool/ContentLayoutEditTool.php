<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Api\LayoutDiagnosticsResultNormalizer;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mcp\LayoutEditOperationFactory;
use Shopware\Core\Framework\ContentSystem\Mcp\LayoutOutlineBuilder;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
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
    name: 'shopware-content-layout-edit',
    title: 'Content Layout Edit',
    description: 'Change a draft of a content layout: applies exactly one operation to the head of a draft branch and saves the result as a new revision of that branch. The live storefront never changes here; going live is a separate step (shopware-content-layout-publish). Get the element ids and slot names from shopware-content-layout-outline and the component names and properties from shopware-content-layout-element-types. Operations and their "arguments" JSON object: '
        . 'insert-element {type, parentElementId?, slot?, index?, properties?} adds a new element (without parentElementId it becomes a root element; properties pre-fill it, e.g. {"text": "<p>Summer Sale</p>"}); '
        . 'set-properties {elementId, properties} merges property values into an element; '
        . 'remove-element {elementId}; '
        . 'move-element {elementId, newParentId?, newSlot?, index?}; '
        . 'duplicate-element {elementId, index?}; '
        . 'replace-element {elementId, newType}; '
        . 'insert-preset {presetId, parentElementId?, slot?}. '
        . 'index is the 0-based position among the siblings, omitted = append. dryRun=true (default) returns the outcome and the diagnostics without saving; set dryRun=false to save the new revision. Returns the new head revision id, the affected elements and the layout diagnostics.'
)]
#[McpToolGroup('content-layout')]
#[McpToolRequires('content_layout:update')]
class ContentLayoutEditTool extends McpToolResponse
{
    private const MAX_CANDIDATES = 3;

    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly LayoutRevisionService $revisionService,
        private readonly LayoutEditOperationFactory $operationFactory,
        private readonly LayoutOutlineBuilder $outlineBuilder,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly StoredTreeCodec $treeCodec,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    /**
     * @param string $layoutId The content layout id (from shopware-content-layout-list).
     * @param string $branchId The draft branch to edit (from shopware-content-layout-branch-create or shopware-content-layout-outline).
     * @param string $operation One of insert-element, set-properties, remove-element, move-element, duplicate-element, replace-element, insert-preset.
     * @param string $arguments JSON object with the operation's arguments, see the tool description.
     * @param string $expectedHead The branch head revision id the edit is based on. Empty uses the current head.
     * @param bool $dryRun true (default) previews without saving; false saves a new revision on the branch.
     */
    public function __invoke(string $layoutId, string $branchId, string $operation, string $arguments = '{}', string $expectedHead = '', bool $dryRun = true): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'content_layout:update')) {
            return $error;
        }

        if (!\in_array($operation, LayoutEditOperationFactory::OPERATIONS, true)) {
            return $this->error(\sprintf('Unknown operation "%s". Use one of: %s.', $operation, implode(', ', LayoutEditOperationFactory::OPERATIONS)));
        }

        $decodedArguments = $this->decodeJsonOrError($arguments, 'arguments');
        if (\is_string($decodedArguments)) {
            return $decodedArguments;
        }

        try {
            $head = $this->revisionService->getBranch($layoutId, $branchId)['head'];

            if ($expectedHead !== '' && $expectedHead !== $head->id) {
                return $this->error(\sprintf('The branch head is "%s", not the expected "%s". Read the draft again with shopware-content-layout-outline and retry.', $head->id, $expectedHead));
            }

            $rootSource = $this->contentLayoutRepository->search(new Criteria([$layoutId]), $context)->getEntities()->first()?->getRootSource();
            $rootContext = $this->rootSourceRegistry->resolveGated($rootSource, $context);
            $result = $this->operationFactory->apply($operation, $decodedArguments, new StoredTree($head->tree), $rootContext);

            if ($dryRun) {
                return $this->success($this->summary($result, $result->layout, $head->id), ['dryRun' => true]);
            }

            $saved = $this->revisionService->saveRevision($layoutId, $branchId, $this->treeCodec->encode($result->layout), $head->id, null, $context);
        } catch (ShopwareHttpException $exception) {
            return $this->error($exception->getMessage());
        }

        return $this->success($this->summary($result, new StoredTree($saved['revision']->tree), $saved['revision']->id), ['dryRun' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(MutationResult $result, StoredTree $tree, string $head): array
    {
        $summary = [
            'head' => $head,
            'affectedElementIds' => $result->affectedElementIds,
            'affected' => $this->outlineBuilder->describe($tree, $result->affectedElementIds),
            'diagnostics' => $this->diagnostics($result->diagnostics),
        ];

        if ($result->orphaned !== []) {
            $summary['orphaned'] = $this->outlineBuilder->build(new StoredTree($result->orphaned));
        }

        if ($result->droppedWiring !== []) {
            $summary['droppedWiring'] = $result->droppedWiring;
        }

        return $summary;
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
