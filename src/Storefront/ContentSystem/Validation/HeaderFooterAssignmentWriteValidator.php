<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The Storefront header/footer assignment gate: a tree-blind type-match of the bound layout's immutable root
 * source against the section id (header / footer).
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
            $section = self::SECTION_BY_ENTITY[$command->getEntityName()] ?? null;

            if ($section === null || !$command->hasField(self::CONTENT_LAYOUT_ID)) {
                continue;
            }

            $rootSource = $this->rootSourceReader->read($command->getPayload()[self::CONTENT_LAYOUT_ID], $event->getCommands(), $context);

            if ($rootSource === null || $rootSource === $section) {
                continue;
            }

            $violations = new ConstraintViolationList([
                ContentSystemException::rootSourceAssignmentMismatchViolation($rootSource, $section, '/' . self::CONTENT_LAYOUT_ID),
            ]);

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }
}
