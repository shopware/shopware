<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Event\Listener;

use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\RenderedTreeEditor;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stamps the layout's scroll-navigation anchors onto the rendered elements they name, so a Twig component or
 * a headless client can read the anchor off the element instead of joining against the page settings. The
 * anchor map lives in `content_layout.settings.scrollNavigation.anchors`, keyed by element id, which is what
 * lets an author mark any element type without the type declaring a property for it.
 *
 * @internal
 */
#[Package('framework')]
class ScrollNavigationAnchorListener implements EventSubscriberInterface
{
    final public const SETTINGS_KEY = 'scrollNavigation';

    final public const ANCHORS_KEY = 'anchors';

    final public const PROPERTY = 'scrollNavigation';

    public static function getSubscribedEvents(): array
    {
        return [
            RenderedTreeFinalizationEvent::class => 'stampAnchors',
        ];
    }

    public function stampAnchors(RenderedTreeFinalizationEvent $event): void
    {
        $anchors = $this->anchors($event->layout->settings);

        if ($anchors === []) {
            return;
        }

        $editor = new RenderedTreeEditor();

        $event->replaceTree($editor->mapNodes($event->tree(), static function (RenderedElement $element) use ($anchors): RenderedElement {
            if (!isset($anchors[$element->id])) {
                return $element;
            }

            return $element->withProperty(self::PROPERTY, $anchors[$element->id]);
        }));
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, array{label: string}>
     */
    private function anchors(array $settings): array
    {
        $scrollNavigation = $settings[self::SETTINGS_KEY] ?? null;

        if (!\is_array($scrollNavigation) || ($scrollNavigation['active'] ?? false) !== true) {
            return [];
        }

        $stored = $scrollNavigation[self::ANCHORS_KEY] ?? null;

        if (!\is_array($stored)) {
            return [];
        }

        $anchors = [];

        foreach ($stored as $elementId => $anchor) {
            if (!\is_string($elementId) || !\is_array($anchor)) {
                continue;
            }

            $label = $anchor['label'] ?? '';

            $anchors[$elementId] = ['label' => \is_string($label) ? $label : ''];
        }

        return $anchors;
    }
}
