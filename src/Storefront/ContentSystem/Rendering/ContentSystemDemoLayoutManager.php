<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemDemoLayoutManager
{
    /**
     * @param EntityRepository<AbstractContentLayoutAssignmentEntity> $landingPageContentLayoutRepository
     */
    public function __construct(
        private readonly EntityRepository $landingPageContentLayoutRepository,
        private readonly EntityRepository $contentLayoutRepository,
    ) {
    }

    /**
     * @param list<int|string> $slotPath
     */
    public function moveElement(
        string $landingPageId,
        SalesChannelContext $salesChannelContext,
        array $slotPath,
        int $fromIndex,
        string $direction,
    ): string {
        $assignment = $this->loadAssignment($landingPageId, $salesChannelContext);
        $contentLayout = $assignment->getContentLayout();

        if ($contentLayout === null) {
            throw new \RuntimeException('No content layout is assigned to this landing page.');
        }

        $layout = $this->normalizeLayout($contentLayout->getLayout());
        $slot = &$this->resolveSlotReference($layout, $slotPath);

        if (!isset($slot[$fromIndex]) || !\is_array($slot[$fromIndex])) {
            throw new \InvalidArgumentException('The requested source element does not exist.');
        }

        $targetIndex = match ($direction) {
            'up' => $fromIndex - 1,
            'down' => $fromIndex + 1,
            default => throw new \InvalidArgumentException('The move direction must be "up" or "down".'),
        };

        if (!isset($slot[$targetIndex]) || !\is_array($slot[$targetIndex])) {
            throw new \InvalidArgumentException('The requested target position is outside the current slot.');
        }

        [$slot[$fromIndex], $slot[$targetIndex]] = [$slot[$targetIndex], $slot[$fromIndex]];
        $slot = \array_values($slot);

        $context = $salesChannelContext->getContext();

        $this->contentLayoutRepository->update([[
            'id' => $contentLayout->getId(),
            'layout' => $layout,
        ]], $context);

        $selectedElement = $slot[$targetIndex] ?? null;

        if (!\is_array($selectedElement) || !isset($selectedElement['id']) || !\is_string($selectedElement['id'])) {
            throw new \RuntimeException('The moved element no longer has a valid ID.');
        }

        return $selectedElement['id'];
    }

    private function loadAssignment(string $landingPageId, SalesChannelContext $salesChannelContext): AbstractContentLayoutAssignmentEntity
    {
        $specificAssignment = $this->searchAssignment($landingPageId, $salesChannelContext->getSalesChannelId(), $salesChannelContext->getContext());

        if ($specificAssignment !== null) {
            return $specificAssignment;
        }

        $fallbackAssignment = $this->searchAssignment($landingPageId, null, $salesChannelContext->getContext());

        if ($fallbackAssignment !== null) {
            return $fallbackAssignment;
        }

        throw new \RuntimeException('No landing-page content layout assignment was found.');
    }

    private function searchAssignment(string $landingPageId, ?string $salesChannelId, Context $context): ?AbstractContentLayoutAssignmentEntity
    {
        $criteria = (new Criteria())->setLimit(1);
        $criteria->addAssociation('contentLayout');
        $criteria->addFilter(new EqualsFilter('landingPageId', $landingPageId));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $assignment = $this->landingPageContentLayoutRepository->search($criteria, $context)->first();

        if (!$assignment instanceof AbstractContentLayoutAssignmentEntity) {
            return null;
        }

        return $assignment;
    }

    /**
     * @param list<mixed> $layout
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeLayout(array $layout): array
    {
        return array_map(function (mixed $element): array {
            if (\is_array($element)) {
                return $element;
            }

            if ($element instanceof ContentElement) {
                return $this->serializeElement($element);
            }

            throw new \InvalidArgumentException('The content layout contains an unsupported element structure.');
        }, $layout);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeElement(ContentElement $element): array
    {
        $serialized = [
            'id' => $element->getId(),
            'component' => $element->getComponent(),
            'properties' => $element->getProperties(),
        ];

        if ($element->getDataRequirements() !== []) {
            throw new \InvalidArgumentException('Demo layout reordering currently supports layout-only elements without data requirements.');
        }

        if (!$element->hasSlots()) {
            return $serialized;
        }

        $slots = [];

        foreach ($element->getSlots() as $slotName => $slotContent) {
            if (!$slotContent instanceof SlotContent) {
                throw new \InvalidArgumentException('The content layout contains an unsupported slot structure.');
            }

            $slots[$slotName] = [];

            foreach ($slotContent as $childElement) {
                if (!$childElement instanceof ContentElement) {
                    throw new \InvalidArgumentException('The content layout contains an unsupported child element structure.');
                }

                $slots[$slotName][] = $this->serializeElement($childElement);
            }
        }

        $serialized['slots'] = $slots;

        return $serialized;
    }

    /**
     * @param list<array<string, mixed>> $layout
     * @param list<int|string> $slotPath
     *
     * @return list<array<string, mixed>>
     */
    private function &resolveSlotReference(array &$layout, array $slotPath): array
    {
        $slot = &$layout;

        foreach ($slotPath as $segment) {
            if (\is_int($segment)) {
                if (!isset($slot[$segment]) || !\is_array($slot[$segment])) {
                    throw new \InvalidArgumentException('The layout path points to a missing element index.');
                }

                $slot = &$slot[$segment];

                continue;
            }

            if (!isset($slot[$segment]) || !\is_array($slot[$segment])) {
                throw new \InvalidArgumentException('The layout path points to a missing container segment.');
            }

            $slot = &$slot[$segment];
        }

        if (!\array_is_list($slot)) {
            throw new \InvalidArgumentException('The resolved layout path is not a movable slot.');
        }

        return $slot;
    }
}
