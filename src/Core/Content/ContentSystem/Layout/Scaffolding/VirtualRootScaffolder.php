<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Wraps layout roots with temporary virtual root to distribute layout-level data as context.
 *
 * Virtual root removed after hydration. Priority 100 (outermost layer).
 *
 * @internal
 */
#[Package('discovery')]
class VirtualRootScaffolder implements LayoutScaffolderInterface
{
    private const VIRTUAL_ROOT_ID = '__page_context_root__';
    private const VIRTUAL_ROOT_TYPE = 'Sw:Internal:PageContext';
    private const VIRTUAL_ROOT_SLOT_NAME = '__page_roots__';

    public static function getPriority(): int
    {
        return 100;
    }

    public function scaffold(
        ContentLayoutEntity $layout,
        RenderingSpecification $specification,
        SalesChannelContext $context
    ): ContentLayoutEntity {
        if (!$this->requiresVirtualRoot($specification, $layout)) {
            return $layout;
        }

        $actualRoots = $layout->getLayout();

        // Virtual root contains layout-level data requirements, exposes loaded data
        // as broadcast context providers, and has actual roots as children in a single slot
        $virtualRoot = new ContentElement(
            id: self::VIRTUAL_ROOT_ID,
            component: self::VIRTUAL_ROOT_TYPE,
            dataRequirements: $this->indexDataRequirements($specification->dataRequirements),
            properties: $specification->placeholderValues->all(),
            slots: [
                self::VIRTUAL_ROOT_SLOT_NAME => new SlotContent($actualRoots),
            ],
            contextDefinitions: $this->createContextDefinitions($specification->dataRequirements)
        );

        $scaffoldedLayout = clone $layout;
        $scaffoldedLayout->setLayout([$virtualRoot]);

        return $scaffoldedLayout;
    }

    public function dismantle(
        ContentLayoutEntity $layout,
        RenderingSpecification $specification,
        SalesChannelContext $context
    ): ContentLayoutEntity {
        if (!$this->requiresVirtualRoot($specification, $layout)) {
            return $layout;
        }

        $roots = $layout->getLayout();

        // Should have exactly one virtual root after scaffolding
        if (\count($roots) !== 1) {
            throw ContentSystemException::pathIntegrityViolation(
                \sprintf(
                    'Expected exactly 1 virtual root after scaffolding, found %d roots. This indicates a scaffolding integrity violation.',
                    \count($roots)
                )
            );
        }

        $virtualRoot = $roots[0];

        $actualRoots = $this->extractActualRoots($virtualRoot);

        $dismantledLayout = clone $layout;
        $dismantledLayout->setLayout($actualRoots);

        return $dismantledLayout;
    }

    // Virtual root needed when page-level data requirements exist and layout has content roots to wrap
    private function requiresVirtualRoot(
        RenderingSpecification $specification,
        ContentLayoutEntity $layout
    ): bool {
        // No data requirements = no need for virtual root
        if ($specification->dataRequirements === []) {
            return false;
        }

        // Empty layout = nothing to wrap
        if ($layout->getLayout() === []) {
            return false;
        }

        return true;
    }

    /**
     * @throws ContentSystemException If element is not a virtual page context root or data integrity violated
     *
     * @return array<ContentElement>
     */
    private function extractActualRoots(ContentElement $virtualRoot): array
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

        // Virtual root should always have the page roots slot
        if ($pageRootsSlot === null) {
            throw ContentSystemException::pathIntegrityViolation(
                \sprintf(
                    'Virtual page context root is missing required slot "%s"',
                    self::VIRTUAL_ROOT_SLOT_NAME
                )
            );
        }

        $extractedRoots = $pageRootsSlot->all();

        // Extracted roots should never be empty if virtual root was properly created
        if ($extractedRoots === []) {
            throw ContentSystemException::pathIntegrityViolation(
                'Virtual page context root slot is empty - roots were lost during hydration'
            );
        }

        return $extractedRoots;
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
                type: ContextType::Single,
                config: new BroadcastDistributionConfig()
            );
        }

        return new ContextDefinitions(providers: $providers, consumers: []);
    }
}
