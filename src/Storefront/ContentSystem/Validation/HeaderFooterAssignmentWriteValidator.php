<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The Storefront header/footer assignment gate: a tree-blind type-match of the assigned layout's immutable root
 * source against the section id (header / footer), once per assignment record on the layout it is assigned after
 * the batch.
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
        foreach (self::SECTION_BY_ENTITY as $entityName => $section) {
            foreach ($this->writeOutcome->resultingRows($event->getCommandsForEntity($entityName), [self::CONTENT_LAYOUT_ID]) as $row) {
                $layoutId = $row->getResultingValue(self::CONTENT_LAYOUT_ID);

                if (\is_string($layoutId)) {
                    $assignments[] = ['row' => $row, 'layoutId' => $layoutId, 'section' => $section];
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

        foreach ($assignments as ['row' => $row, 'layoutId' => $layoutId, 'section' => $section]) {
            $rootSource = $rootSources[$layoutId] ?? null;

            // A null root source means the layout is not loadable, which the FK constraint rejects anyway.
            if ($rootSource === null || $rootSource === $section) {
                continue;
            }

            $violations = new ConstraintViolationList([
                ContentSystemException::rootSourceAssignmentMismatchViolation($rootSource, $section, '/' . self::CONTENT_LAYOUT_ID),
            ]);

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $row->path));
        }
    }
}
