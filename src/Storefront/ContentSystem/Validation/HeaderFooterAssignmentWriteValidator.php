<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Binding\SourceBinding;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutBindingChecker;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Storefront header/footer assignment write validator: applies the shared binding check to the
 * header/footer assignment entities. Both sections expose no root-ambient context, so binding a layout to a
 * header or footer requires it to be fully resolvable without page data. Delegates the load-tree → resolvability
 * path to the shared Core {@see LayoutBindingChecker}.
 *
 * @internal
 */
#[Package('framework')]
class HeaderFooterAssignmentWriteValidator implements EventSubscriberInterface
{
    private const CONTENT_LAYOUT_ID = 'content_layout_id';

    private const SECTION_BY_ENTITY = [
        HeaderContentLayoutDefinition::ENTITY_NAME => ContentSection::HEADER->value,
        FooterContentLayoutDefinition::ENTITY_NAME => ContentSection::FOOTER->value,
    ];

    public function __construct(
        private readonly LayoutGate $gate,
        private readonly LayoutBindingChecker $bindingChecker,
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
            $section = self::SECTION_BY_ENTITY[$command->getEntityName()] ?? null;

            if ($section === null || !$command->hasField(self::CONTENT_LAYOUT_ID)) {
                continue;
            }

            if (!$this->gate->isBindingEnforced(new SourceBinding($section, []))) {
                continue;
            }

            $violations = $this->bindingChecker->bindingViolations($command->getPayload()[self::CONTENT_LAYOUT_ID], [], $event->getCommands(), $context);

            if ($violations->count() === 0) {
                continue;
            }

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }
}
