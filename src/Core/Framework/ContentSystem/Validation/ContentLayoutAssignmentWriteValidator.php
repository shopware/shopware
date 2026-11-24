<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The entity-assignment gate: a tree-blind type-match of the assigned layout's immutable root source against the
 * assignment definition's entity type, once per assignment record on the layout it is assigned after the batch. Do not
 * add a tree re-check here: it would be an opportunistic write-time check at the wrong layer.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentLayoutAssignmentWriteValidator implements EventSubscriberInterface
{
    private const CONTENT_LAYOUT_ID = 'content_layout_id';

    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly LayoutRootSourceReader $rootSourceReader,
        private readonly WriteOutcome $writeOutcome,
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
        if ($event->getContext()->hasState(LayoutGate::SKIP_VALIDATION_STATE)) {
            return;
        }

        $assignments = [];
        foreach ($this->assignmentDefinitions($event->getCommands()) as $entityName => $definition) {
            foreach ($this->writeOutcome->resultingRows($event->getCommandsForEntity($entityName), [self::CONTENT_LAYOUT_ID]) as $row) {
                $layoutId = $row->getResultingValue(self::CONTENT_LAYOUT_ID);

                if (\is_string($layoutId)) {
                    $assignments[] = ['row' => $row, 'layoutId' => $layoutId, 'expected' => $definition->getContentLayoutEntityType()];
                }
            }
        }

        if ($assignments === []) {
            return;
        }

        $rootSources = $this->rootSourceReader->rootSources(
            array_column($assignments, 'layoutId'),
            $event->getCommandsForEntity(ContentLayoutDefinition::ENTITY_NAME),
        );

        foreach ($assignments as $assignment) {
            $this->validateAssignment($event, $assignment['row'], $rootSources[$assignment['layoutId']] ?? null, $assignment['expected']);
        }
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array<string, AbstractContentLayoutAssignableDefinition> keyed by entity name
     */
    private function assignmentDefinitions(array $commands): array
    {
        $definitions = [];
        foreach ($commands as $command) {
            $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());

            if ($definition instanceof AbstractContentLayoutAssignableDefinition) {
                $definitions[$command->getEntityName()] = $definition;
            }
        }

        return $definitions;
    }

    private function validateAssignment(PreWriteValidationEvent $event, OutcomeRow $row, ?string $rootSource, string $expected): void
    {
        // A null root source means the layout is not loadable (a non-existent id the FK constraint rejects anyway);
        // a match is the served-implies-resolvable case. Only a real mismatch is rejected.
        if ($rootSource === null || $rootSource === $expected) {
            return;
        }

        $violations = new ConstraintViolationList([
            ContentSystemException::rootSourceAssignmentMismatchViolation($rootSource, $expected, '/' . self::CONTENT_LAYOUT_ID),
        ]);

        $event->getExceptions()->add(new WriteConstraintViolationException($violations, $row->path));
    }
}
