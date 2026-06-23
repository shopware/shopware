<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Shared binding-check core: applies the resolvability gate to a content layout referenced by id and returns the
 * binding-scope violations it has against a source's provided root context. It resolves the layout's tree (from
 * the current write batch or the committed store), asks {@see LayoutGate} for resolvability, and maps the report
 * to violations. The Core entity-assignment validator and the Storefront header/footer validator both delegate
 * here, so the "resolve tree → resolvability → map" path is single-sourced across sections.
 *
 * The tree is resolved from the current write batch first (so an atomic create-and-bind, where the layout is not
 * yet committed, is validated against its in-flight INSERT) and only then from the committed store.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutBindingChecker
{
    public function __construct(
        private readonly LayoutGate $gate,
        private readonly ViolationConstraintMapper $violationMapper,
        private readonly LayoutTreeDecoder $treeDecoder,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    /**
     * @param list<ProvidedContext> $providedRootContext
     * @param list<WriteCommand> $commands the current write batch (from PreWriteValidationEvent)
     */
    public function bindingViolations(mixed $contentLayoutId, array $providedRootContext, array $commands, Context $context): ConstraintViolationList
    {
        $layoutId = $this->normalizeId($contentLayoutId);

        if ($layoutId === null) {
            return new ConstraintViolationList();
        }

        $tree = $this->treeFromCommands($layoutId, $commands) ?? $this->loadTreeFromDb($layoutId, $context);

        if ($tree === null) {
            return new ConstraintViolationList();
        }

        $report = $this->gate->resolvability($tree, $providedRootContext, $context);

        return $this->violationMapper->toConstraintViolationList($report->bindingErrors());
    }

    private function normalizeId(mixed $contentLayoutId): ?string
    {
        if (!\is_string($contentLayoutId) || $contentLayoutId === '') {
            return null;
        }

        return \strlen($contentLayoutId) === 16 ? Uuid::fromBytesToHex($contentLayoutId) : $contentLayoutId;
    }

    /**
     * Resolves the in-flight tree of a layout being written in the same batch as the binding. Returns null when
     * the layout is not in the batch, the matching command does not touch the layout column, or the in-batch tree
     * is a client-side defect — in every such case the committed-store fallback decides. A client-defect decode is
     * deliberately swallowed: the well-formedness validator ({@see ContentLayoutWriteValidator}) already records it
     * for the same in-batch command, so re-reporting here would duplicate the violation.
     *
     * @param list<WriteCommand> $commands
     *
     * @return list<ContentElement>|null
     */
    private function treeFromCommands(string $layoutId, array $commands): ?array
    {
        foreach ($commands as $command) {
            if ($command->getEntityName() !== ContentLayoutDefinition::ENTITY_NAME || $command instanceof DeleteCommand) {
                continue;
            }

            if (($command->getDecodedPrimaryKey()['id'] ?? null) !== $layoutId) {
                continue;
            }

            if (!$command->hasField(ContentLayoutDefinition::LAYOUT_FIELD)) {
                return null;
            }

            try {
                return $this->treeDecoder->decode($command->getPayload()[ContentLayoutDefinition::LAYOUT_FIELD]);
            } catch (ContentSystemException $exception) {
                if (ContentSystemException::isClientDefect($exception)) {
                    return null;
                }

                throw $exception;
            }
        }

        return null;
    }

    /**
     * Loads the bound layout's tree from the committed store. Returns null when the layout is not loadable —
     * either because it does not exist (the FK constraint guards that) or because it is being created in the same
     * uncommitted transaction without an in-batch tree to validate.
     *
     * @return list<ContentElement>|null
     */
    private function loadTreeFromDb(string $layoutId, Context $context): ?array
    {
        $layout = $this->definitionRegistry->getRepository(ContentLayoutDefinition::ENTITY_NAME)->search(new Criteria([$layoutId]), $context)->first();

        return $layout instanceof ContentLayoutEntity ? $layout->getLayout() : null;
    }
}
