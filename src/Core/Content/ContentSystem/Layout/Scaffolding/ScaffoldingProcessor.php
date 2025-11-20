<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Orchestrates layout scaffolding in priority order (scaffold: high→low, dismantle: low→high).
 *
 * @internal
 */
#[Package('discovery')]
class ScaffoldingProcessor
{
    /**
     * @var array<LayoutScaffolderInterface> Scaffolders sorted for scaffold() execution (highest → lowest priority)
     */
    private readonly array $scaffoldersForScaffolding;

    /**
     * @var array<LayoutScaffolderInterface> Scaffolders sorted for dismantle() execution (lowest → highest priority)
     */
    private readonly array $scaffoldersForDismantling;

    /**
     * @param iterable<LayoutScaffolderInterface> $scaffolders Tagged scaffolders (pre-sorted by DI)
     */
    public function __construct(iterable $scaffolders)
    {
        // Convert to array once - scaffolders already sorted by priority (highest → lowest)
        $this->scaffoldersForScaffolding = iterator_to_array($scaffolders, false);

        // Pre-compute reversed order for dismantling (lowest → highest)
        $this->scaffoldersForDismantling = array_reverse($this->scaffoldersForScaffolding);
    }

    /**
     * Applies scaffolding to layout (executes scaffolders highest → lowest priority).
     */
    public function scaffold(
        ContentLayoutEntity $layout,
        RenderingSpecification $specification,
        SalesChannelContext $context
    ): ContentLayoutEntity {
        foreach ($this->scaffoldersForScaffolding as $scaffolder) {
            $layout = $scaffolder->scaffold($layout, $specification, $context);
        }

        return $layout;
    }

    /**
     * Removes scaffolding from hydrated layout (executes scaffolders in REVERSE priority: lowest → highest).
     */
    public function dismantle(
        ContentLayoutEntity $layout,
        RenderingSpecification $specification,
        SalesChannelContext $context
    ): ContentLayoutEntity {
        foreach ($this->scaffoldersForDismantling as $scaffolder) {
            $layout = $scaffolder->dismantle($layout, $specification, $context);
        }

        return $layout;
    }
}
