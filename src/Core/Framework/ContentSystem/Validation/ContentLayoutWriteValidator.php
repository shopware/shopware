<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteContext;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The single ordered content_layout write gate: well-formedness on the decoded tree, then membership of the
 * record's root source in the registry, then resolvability of the tree against that source's root-ambient context,
 * then admissibility of the tree's data mappings against that same source.
 * Each layout record gets one verdict, on its state after the whole batch: the last tree the batch writes for it,
 * judged against its resulting root source, the created value or else the stored one.
 * The per-step rationale is inline in validateRecord().
 *
 * The tree is not decoded here. The layout field serializer decoded it, admitted it through the write boundary
 * and left it on the write `Context` ({@see LayoutWriteContext}), so this gate judges the tree that is about to
 * be stored rather than a second decode of the same column. Reading the memo consumes it, on the skip path as
 * well as the checking path, so a write that reaches this subscriber leaves nothing behind. Only the memo this
 * write opened is read: one left by an earlier write on the same `Context` counts as absent.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentLayoutWriteValidator implements EventSubscriberInterface
{
    public function __construct(
        private readonly LayoutGate $gate,
        private readonly ViolationConstraintMapper $violationMapper,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly WriteOutcome $writeOutcome,
        private readonly StoredMappingValidator $mappingValidator,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();
        $extension = $context->getExtension(LayoutWriteContext::EXTENSION_NAME);
        $memo = $extension instanceof LayoutWriteContext && $extension->ownedBy($event->getWriteContext())
            ? $extension
            : null;

        $layoutCommands = $event->getCommandsForEntity(ContentLayoutDefinition::ENTITY_NAME);

        if ($context->hasState(LayoutGate::SKIP_VALIDATION_STATE)) {
            $this->drain($memo, $layoutCommands);

            return;
        }

        foreach ($this->writeOutcome->touchedRows($layoutCommands, [ContentLayoutDefinition::ROOT_SOURCE_FIELD]) as $row) {
            $this->validateRecord($event, $row, $context, $memo);
        }
    }

    private function validateRecord(PreWriteValidationEvent $event, OutcomeRow $row, Context $context, ?LayoutWriteContext $memo): void
    {
        $tree = $this->resultingTree($memo, $row);
        $setsRootSource = $row->payloadContainsAny(ContentLayoutDefinition::ROOT_SOURCE_FIELD);

        if ($tree === null && !$setsRootSource) {
            return;
        }

        $rootSource = $row->getResultingValue(ContentLayoutDefinition::ROOT_SOURCE_FIELD);
        $violations = new ConstraintViolationList();

        // Step 1: well-formedness on the decoded tree (only when the batch writes the layout column).
        if ($tree !== null) {
            $violations->addAll($this->violationMapper->toConstraintViolationList(
                $this->gate->wellFormedness($tree->roots)->intrinsicErrors()
            ));
        }

        // Step 2: membership of the resulting root source, so resolve() is never handed an unregistered id. On a
        // layout-only edit it is the stored source: one removed from the registry after the layout was written
        // surfaces as the unknownRootSource 400 instead of the resolve() 500.
        if (($setsRootSource || \is_string($rootSource)) && !$this->isRegistered($rootSource)) {
            $violations->add($this->unknownRootSourceViolation($rootSource));
            $this->collect($event, $row, $violations);

            return;
        }

        // Step 3: resolvability against that root source. A record without one (a stored row that reads null) is
        // left to the DAL's own checks.
        //
        // resolvability() re-runs the analysis; its intrinsic checks are root-context-independent, so it recomputes
        // step 1's intrinsic errors identically and we discard them, keeping only bindingErrors(). The redundant
        // intrinsic pass is deliberate, not a correctness guard: the three steps gate on independent conditions, and
        // step 1 must run on its own for the records that never reach here (the unknown-source early-return, or an
        // edit whose stored source reads null), so folding intrinsic out of step 1 to reuse this report would
        // entangle the three steps to spare one analyze() pass on a bounded admin write.
        if ($tree !== null && \is_string($rootSource)) {
            $report = $this->gate->resolvability($tree->roots, $this->rootSourceRegistry->resolve($rootSource, $context), $rootSource);
            $violations->addAll($this->violationMapper->toConstraintViolationList($report->bindingErrors()));

            // Step 4: admissibility of every stored data mapping against that same root source. It runs here
            // rather than as generic mapping diagnostics so each rule retains its specific error code.
            $violations->addAll($this->mappingValidator->validate($tree->roots, $rootSource));
        }

        $this->collect($event, $row, $violations);
    }

    /**
     * The last tree the batch writes for this record, taken out of the memo. Every write of the layout column put
     * one there: the serializer memoizes before this event can fire, and a payload it could not decode aborted the
     * write before any command existed. So an absent entry is a broken write path, not an input the gate could
     * rule on: re-decoding the column instead would silently gate a tree other than the one the boundary produced,
     * which is precisely the guarantee this gate exists to hold.
     */
    private function resultingTree(?LayoutWriteContext $memo, OutcomeRow $row): ?StoredTree
    {
        $tree = $memo?->consume(ContentLayoutDefinition::ENTITY_NAME, Uuid::fromBytesToHex($row->primaryKey['id']));

        // Consumed either way: a tree written before the record's last delete is not one the batch stores.
        if (!$row->payloadContainsAny(ContentLayoutDefinition::LAYOUT_FIELD)) {
            return null;
        }

        if ($tree === null) {
            throw ContentSystemException::layoutWriteMemoMissing(ContentLayoutDefinition::ENTITY_NAME, $row->path);
        }

        return $tree;
    }

    /**
     * Consumes the memo entries of this event's layout commands without gating them, for the skip-state path.
     * The state suppresses the checks; it does not make the write's memoized trees somebody else's to clear.
     *
     * @param list<WriteCommand> $layoutCommands
     */
    private function drain(?LayoutWriteContext $memo, array $layoutCommands): void
    {
        if ($memo === null) {
            return;
        }

        foreach ($layoutCommands as $command) {
            $memo->consume(ContentLayoutDefinition::ENTITY_NAME, $command->getDecodedPrimaryKey()['id']);
        }
    }

    private function isRegistered(mixed $rootSource): bool
    {
        return \is_string($rootSource) && \in_array($rootSource, $this->rootSourceRegistry->knownRootSources(), true);
    }

    private function unknownRootSourceViolation(mixed $rootSource): ConstraintViolation
    {
        $exception = ContentSystemException::unknownRootSource(\is_string($rootSource) ? $rootSource : \get_debug_type($rootSource));

        return new ConstraintViolation(
            $exception->getMessage(),
            $exception->getMessage(),
            [],
            null,
            '/' . ContentLayoutDefinition::ROOT_SOURCE_FIELD,
            $rootSource,
            null,
            $exception->getErrorCode(),
        );
    }

    private function collect(PreWriteValidationEvent $event, OutcomeRow $row, ConstraintViolationList $violations): void
    {
        if ($violations->count() === 0) {
            return;
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations, $row->path));
    }
}
