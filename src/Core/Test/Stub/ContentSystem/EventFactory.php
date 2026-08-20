<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
#[Package('framework')]
class EventFactory
{
    private function __construct()
    {
    }

    /**
     * @param list<StoredElement> $tree
     * @param list<DataRequirement> $dataRequirements
     */
    public static function treePreparation(
        array $tree,
        array $dataRequirements = [],
        ?string $targetElementId = null,
        ?PlaceholderValues $placeholderValues = null,
    ): ContentTreePreparationEvent {
        return new ContentTreePreparationEvent(
            $tree,
            LayoutReference::create('layout-1', 'Test', null),
            new RenderingSpecification(
                $dataRequirements,
                $placeholderValues ?? PlaceholderValues::from([]),
                new Request(),
                $targetElementId,
            ),
            Generator::generateSalesChannelContext(),
            new RenderingCacheContext(),
        );
    }

    /**
     * @param list<RenderedElement> $tree
     * @param list<DataRequirement> $dataRequirements
     */
    public static function renderedTreeFinalization(
        array $tree,
        array $dataRequirements = [],
        ?string $targetElementId = null,
    ): RenderedTreeFinalizationEvent {
        return new RenderedTreeFinalizationEvent(
            $tree,
            LayoutReference::create('layout-1', 'Test', null),
            new RenderingSpecification(
                $dataRequirements,
                PlaceholderValues::from([]),
                new Request(),
                $targetElementId,
            ),
            Generator::generateSalesChannelContext(),
            new RenderingCacheContext(),
        );
    }
}
