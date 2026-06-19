<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Binding\BoundRootContext;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The binding gate (§8.2): a single generic subscriber on every Core entity-assignment entity. When a source is
 * bound to a layout it derives the bound source's root context from the written assignment's definition and
 * accumulates binding-scope violations via the shared {@see LayoutBindingGate}, establishing the
 * served-implies-resolvable invariant when the assignment and its layout are written in separate transactions —
 * the common case on every path including the Sync API. A single Sync batch that creates both the layout and its
 * binding at once is the one gap: the layout is not yet committed when this event fires, so {@see LayoutBindingGate}
 * cannot load its tree and skips the check; the §8.3 re-check on the next edit of that layout closes it. The
 * provided-context computation needs only Context — no sales-channel state — so the DAL boundary, which has no
 * SalesChannelContext, suffices.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutAssignmentWriteValidator implements EventSubscriberInterface
{
    private const CONTENT_LAYOUT_ID = 'content_layout_id';

    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly RootContextMapper $rootContextMapper,
        private readonly LayoutResolvabilityValidator $resolvabilityValidator,
        private readonly LayoutBindingGate $bindingGate,
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

        if ($context->hasState(LayoutResolvabilityValidator::SKIP_VALIDATION_STATE)) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            $definition = $this->resolveAssignmentDefinition($command);

            if ($definition === null || !$command->hasField(self::CONTENT_LAYOUT_ID)) {
                continue;
            }

            $this->validateBinding($event, $command, $definition, $context);
        }
    }

    private function resolveAssignmentDefinition(WriteCommand $command): ?AbstractContentLayoutAssignableDefinition
    {
        $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());

        return $definition instanceof AbstractContentLayoutAssignableDefinition ? $definition : null;
    }

    private function validateBinding(
        PreWriteValidationEvent $event,
        WriteCommand $command,
        AbstractContentLayoutAssignableDefinition $definition,
        Context $context,
    ): void {
        $providedRootContext = $this->rootContextMapper->map($definition->getPageDataRequirements());
        $binding = new BoundRootContext($definition->getContentLayoutEntityType(), $providedRootContext);

        if (!$this->resolvabilityValidator->isBindingEnforced($binding)) {
            return;
        }

        $violations = $this->bindingGate->bindingViolations(
            $command->getPayload()[self::CONTENT_LAYOUT_ID],
            $providedRootContext,
            $context,
        );

        if ($violations->count() === 0) {
            return;
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
    }
}
