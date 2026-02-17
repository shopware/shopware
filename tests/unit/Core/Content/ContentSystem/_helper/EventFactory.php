<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\_helper;

use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
final class EventFactory
{
    private function __construct()
    {
    }

    /**
     * @param list<ContentElement> $elements
     * @param list<DataRequirement> $dataRequirements
     */
    public static function preHydration(
        array $elements,
        array $dataRequirements = [],
        ?string $targetElementId = null,
        ?PlaceholderValues $placeholderValues = null,
    ): PreContentHydrationEvent {
        return new PreContentHydrationEvent(
            $elements,
            'layout-1',
            'Test',
            null,
            new RenderingSpecification(
                'layout-1',
                $dataRequirements,
                $placeholderValues ?? PlaceholderValues::from([]),
                new Request(),
                $targetElementId,
            ),
            RenderingMode::FULL,
            Generator::generateSalesChannelContext(),
            new RenderingCacheContext(),
        );
    }

    /**
     * @param list<ContentElement> $elements
     * @param list<DataRequirement> $dataRequirements
     */
    public static function postHydration(
        array $elements,
        array $dataRequirements = [],
        ?string $targetElementId = null,
    ): PostHydrationEvent {
        return new PostHydrationEvent(
            $elements,
            'layout-1',
            'Test',
            null,
            new RenderingSpecification(
                'layout-1',
                $dataRequirements,
                PlaceholderValues::from([]),
                new Request(),
                $targetElementId,
            ),
            RenderingMode::FULL,
            Generator::generateSalesChannelContext(),
            new RenderingCacheContext(),
        );
    }
}
