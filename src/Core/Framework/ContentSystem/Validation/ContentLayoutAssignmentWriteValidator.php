<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The entity-assignment gate: a tree-blind type-match of the bound layout's immutable root source against the
 * assignment definition's entity type. Do not add a tree re-check here: it would be an opportunistic write-time
 * check at the wrong layer.
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

        if ($context->hasState(LayoutGate::SKIP_VALIDATION_STATE)) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            $definition = $this->resolveAssignmentDefinition($command);

            if ($definition === null || !$command->hasField(self::CONTENT_LAYOUT_ID)) {
                continue;
            }

            $this->validateAssignment($event, $command, $definition, $context);
        }
    }

    private function resolveAssignmentDefinition(WriteCommand $command): ?AbstractContentLayoutAssignableDefinition
    {
        $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());

        return $definition instanceof AbstractContentLayoutAssignableDefinition ? $definition : null;
    }

    private function validateAssignment(
        PreWriteValidationEvent $event,
        WriteCommand $command,
        AbstractContentLayoutAssignableDefinition $definition,
        Context $context,
    ): void {
        $rootSource = $this->rootSourceReader->read($command->getPayload()[self::CONTENT_LAYOUT_ID], $event->getCommands(), $context);
        $expected = $definition->getContentLayoutEntityType();

        // A null root source means the layout is not loadable (a non-existent id the FK constraint rejects anyway);
        // a match is the served-implies-resolvable case. Only a real mismatch is rejected.
        if ($rootSource === null || $rootSource === $expected) {
            return;
        }

        $violations = new ConstraintViolationList([
            ContentSystemException::rootSourceAssignmentMismatchViolation($rootSource, $expected, '/' . self::CONTENT_LAYOUT_ID),
        ]);

        $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
    }
}
