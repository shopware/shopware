<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Handles virtual root wrapping and unwrapping for page-level context distribution.
 *
 * Virtual root is a temporary structural modification (scaffolding) that wraps actual layout
 * roots to enable page-level data requirements to be distributed as broadcast context.
 *
 * @internal
 */
#[Package('discovery')]
final class VirtualRootWrapper
{
    private const VIRTUAL_ROOT_ID = '__page_context_root__';
    private const VIRTUAL_ROOT_TYPE = 'Sw:Internal:PageContext';
    private const VIRTUAL_ROOT_SLOT_NAME = '__page_roots__';

    /**
     * Determines if virtual root wrapping is required.
     *
     * Virtual root is needed when page-level data requirements exist and layout has content roots to wrap.
     *
     * @param array<ContentElement> $elements
     */
    public function requiresWrapping(RenderingSpecification $specification, array $elements): bool
    {
        if ($specification->dataRequirements === []) {
            return false;
        }

        if ($elements === []) {
            return false;
        }

        return true;
    }

    /**
     * Creates virtual root wrapper containing actual layout roots.
     *
     * Virtual root contains layout-level data requirements, exposes loaded data
     * as broadcast context providers, and has actual roots as children in a single slot.
     *
     * @param array<ContentElement> $actualRoots
     */
    public function wrap(array $actualRoots, RenderingSpecification $specification): ContentElement
    {
        return new ContentElement(
            self::VIRTUAL_ROOT_ID,
            self::VIRTUAL_ROOT_TYPE,
            $this->indexDataRequirements($specification->dataRequirements),
            $specification->placeholderValues->all(),
            [self::VIRTUAL_ROOT_SLOT_NAME => new SlotContent($actualRoots)],
            $this->createContextDefinitions($specification->dataRequirements)
        );
    }

    /**
     * Extracts actual roots from virtual root wrapper with validation.
     *
     * @throws ContentSystemException If element is not a virtual root or data integrity violated
     *
     * @return list<ContentElement>
     */
    public function unwrap(ContentElement $virtualRoot): array
    {
        if ($virtualRoot->getId() !== self::VIRTUAL_ROOT_ID) {
            throw ContentSystemException::pathIntegrityViolation(
                \sprintf(
                    'Expected virtual page context root with ID "%s", got element with ID "%s" and component "%s"',
                    self::VIRTUAL_ROOT_ID,
                    $virtualRoot->getId(),
                    $virtualRoot->getComponent()
                )
            );
        }

        $slots = $virtualRoot->getSlots();
        $pageRootsSlot = $slots[self::VIRTUAL_ROOT_SLOT_NAME] ?? null;

        if ($pageRootsSlot === null) {
            throw ContentSystemException::pathIntegrityViolation(
                \sprintf(
                    'Virtual page context root is missing required slot "%s"',
                    self::VIRTUAL_ROOT_SLOT_NAME
                )
            );
        }

        $extractedRoots = $pageRootsSlot->getElements();

        if ($extractedRoots === []) {
            throw ContentSystemException::pathIntegrityViolation(
                'Virtual page context root slot is empty - roots were lost during hydration'
            );
        }

        return array_values($extractedRoots);
    }

    /**
     * Checks if element is the virtual root wrapper.
     */
    public function isVirtualRoot(ContentElement $element): bool
    {
        return $element->getId() === self::VIRTUAL_ROOT_ID;
    }

    /**
     * Index data requirements by key for O(1) lookups.
     *
     * @param array<DataRequirement> $requirements
     *
     * @return array<string, DataRequirement>
     */
    private function indexDataRequirements(array $requirements): array
    {
        $indexed = [];

        foreach ($requirements as $requirement) {
            $indexed[$requirement->key] = $requirement;
        }

        return $indexed;
    }

    /**
     * Create broadcast providers for layout-level data requirements.
     *
     * @param array<DataRequirement> $layoutDataRequirements
     */
    private function createContextDefinitions(array $layoutDataRequirements): ContextDefinitions
    {
        $providers = [];

        foreach ($layoutDataRequirements as $requirement) {
            $providers[$requirement->key] = new ContextProvider(
                ContextType::Single,
                BroadcastDistributionConfig::simple()
            );
        }

        return new ContextDefinitions($providers, []);
    }
}
