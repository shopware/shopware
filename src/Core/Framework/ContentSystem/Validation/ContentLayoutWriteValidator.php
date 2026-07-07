<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
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
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The single ordered content_layout write gate: well-formedness on the decoded tree, then membership of the
 * written root source in the registry, then resolvability of the tree against that source's root-ambient context.
 * The per-step rationale — why membership is gated before resolvability, and why the edit path re-checks the
 * committed source — is inline in validateCommand().
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
        private readonly LayoutTreeDecoder $treeDecoder,
        private readonly RootSourceRegistry $rootSourceRegistry,
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
            if ($command->getEntityName() !== ContentLayoutDefinition::ENTITY_NAME) {
                continue;
            }

            $this->validateCommand($event, $command, $context);
        }
    }

    private function validateCommand(PreWriteValidationEvent $event, WriteCommand $command, Context $context): void
    {
        $touchesLayout = $command->hasField(ContentLayoutDefinition::LAYOUT_FIELD);
        $setsRootSource = $command->hasField(ContentLayoutDefinition::ROOT_SOURCE_FIELD);

        if (!$touchesLayout && !$setsRootSource) {
            return;
        }

        $payload = $command->getPayload();
        $violations = new ConstraintViolationList();

        // Step 1: well-formedness on the decoded tree (only when the write touches the layout column).
        $tree = $touchesLayout ? $this->decodeTree($payload[ContentLayoutDefinition::LAYOUT_FIELD] ?? null, $violations) : null;

        if ($tree !== null) {
            $violations->addAll($this->violationMapper->toConstraintViolationList(
                $this->gate->wellFormedness($tree)->intrinsicErrors()
            ));
        }

        // Step 2: membership of the written root source. On creation the row sets root_source; an unknown value is
        // rejected here and step 3 is skipped, so resolve() is never handed an unregistered id.
        if ($setsRootSource) {
            $rootSource = $payload[ContentLayoutDefinition::ROOT_SOURCE_FIELD];

            if (!\is_string($rootSource) || !\in_array($rootSource, $this->rootSourceRegistry->knownRootSources(), true)) {
                $violations->add($this->unknownRootSourceViolation($rootSource));
                $this->collect($event, $command, $violations);

                return;
            }
        }

        // Step 3: resolvability against the declared root source (the create payload value, else the committed row
        // read through LayoutRootSourceReader). On the create path membership was already gated in step 2; on a
        // layout-only edit the committed source is gated here before resolve() — a source de-registered after the
        // layout was written, or a stray non-member value, surfaces as the unknownRootSource 400 instead of the
        // resolve() 500. After this, resolve() is never handed an unregistered id on either path.
        //
        // resolvability() re-runs the analysis; its intrinsic checks are root-context-independent, so it recomputes
        // step 1's intrinsic errors identically and we discard them, keeping only bindingErrors(). The redundant
        // intrinsic pass is deliberate, not a correctness guard: the three steps gate on independent conditions, and
        // step 1 must run on its own for the writes that never reach here (the unknown-source early-return, or an edit
        // whose committed source reads null), so folding intrinsic out of step 1 to reuse this report would entangle
        // the three steps to spare one analyze() pass on a bounded admin write.
        if ($tree !== null) {
            $rootSource = $setsRootSource
                ? $payload[ContentLayoutDefinition::ROOT_SOURCE_FIELD]
                : $this->rootSourceReader->read($command->getDecodedPrimaryKey()['id'] ?? null, $event->getCommands(), $context);

            if (\is_string($rootSource)) {
                if (!$setsRootSource && !\in_array($rootSource, $this->rootSourceRegistry->knownRootSources(), true)) {
                    $violations->add($this->unknownRootSourceViolation($rootSource));
                    $this->collect($event, $command, $violations);

                    return;
                }

                $report = $this->gate->resolvability($tree, $this->rootSourceRegistry->resolve($rootSource, $context));
                $violations->addAll($this->violationMapper->toConstraintViolationList($report->bindingErrors()));
            }
        }

        $this->collect($event, $command, $violations);
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

    private function collect(PreWriteValidationEvent $event, WriteCommand $command, ConstraintViolationList $violations): void
    {
        if ($violations->count() === 0) {
            return;
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
    }
}
