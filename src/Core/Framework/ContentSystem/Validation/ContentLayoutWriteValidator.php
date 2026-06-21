<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Binding\LayoutBindingEnumerator;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The well-formedness gate and the synchronous re-check of already-bound layouts. On every
 * content_layout write touching the layout column it decodes the tree, accumulates intrinsic-scope violations
 * (well-formedness — gates persistence), then re-validates the same tree against each distinct source bound to
 * the layout via the tagged binding enumerators, accumulating binding-scope violations so an edit cannot make a
 * live layout unresolvable and reach serving. An incomplete or unbound layout persists freely.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutWriteValidator implements EventSubscriberInterface
{
    /**
     * @param iterable<LayoutBindingEnumerator> $bindingEnumerators
     */
    public function __construct(
        private readonly LayoutResolvabilityValidator $resolvabilityValidator,
        private readonly ViolationConstraintMapper $violationMapper,
        private readonly LayoutTreeDecoder $treeDecoder,
        private readonly iterable $bindingEnumerators,
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
            if ($command->getEntityName() !== ContentLayoutDefinition::ENTITY_NAME || !$command->hasField(ContentLayoutDefinition::LAYOUT_FIELD)) {
                continue;
            }

            $this->validateCommand($event, $command, $context);
        }
    }

    private function validateCommand(PreWriteValidationEvent $event, WriteCommand $command, Context $context): void
    {
        $payload = $command->getPayload();

        $violations = new ConstraintViolationList();

        $tree = $this->decodeTree($payload[ContentLayoutDefinition::LAYOUT_FIELD] ?? null, $violations);

        if ($tree !== null) {
            $report = $this->resolvabilityValidator->wellFormedness($tree, $context);
            $violations->addAll($this->violationMapper->toConstraintViolationList($report->intrinsicErrors()));

            $violations->addAll($this->recheckBoundSources($tree, $command, $context));
        }

        if ($violations->count() === 0) {
            return;
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
    }

    /**
     * @return list<ContentElement>|null null when the tree could not be decoded (an invalid_config violation was recorded)
     */
    private function decodeTree(mixed $value, ConstraintViolationList $violations): ?array
    {
        try {
            return $this->treeDecoder->decode($value);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            $violations->addAll($this->violationMapper->toConstraintViolationList([
                new Violation(ViolationCode::InvalidConfig, '', null, $exception->getMessage()),
            ]));

            return null;
        }
    }

    /**
     * Re-validates the written tree against every source currently bound to the layout. The binding
     * enumerators read committed bindings, so a single batch that both makes the layout unresolvable for a
     * source and deletes the only binding to that source still re-checks against it and over-rejects. This
     * errs safe (it never accepts a write that leaves a live binding unresolvable); the trigger is contrived.
     *
     * @param list<ContentElement> $tree
     */
    private function recheckBoundSources(array $tree, WriteCommand $command, Context $context): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        $contentLayoutId = $command->getDecodedPrimaryKey()['id'] ?? null;

        if ($contentLayoutId === null) {
            return $violations;
        }

        foreach ($this->bindingEnumerators as $enumerator) {
            foreach ($enumerator->enumerate($contentLayoutId, $context) as $binding) {
                if (!$this->resolvabilityValidator->isBindingEnforced($binding)) {
                    continue;
                }

                $report = $this->resolvabilityValidator->resolvability($tree, $binding->providedRootContext, $context);
                $violations->addAll($this->violationMapper->toConstraintViolationList($report->bindingErrors()));
            }
        }

        return $violations;
    }
}
