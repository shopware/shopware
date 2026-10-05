# Listener API

## Working with RenderedElement

`RenderedElement` is the tree node a `RenderedTreeFinalizationEvent` listener works against (a `ContentTreePreparationEvent` listener works against `StoredElement` instead). It is `final readonly`, so every edit returns a new instance.

A `null` property is present, not absent: `array_key_exists()` on `$properties` tells the two apart ([rule](../../../docs/principles/rendering.md#a-null-in-the-rendered-map-means-a-resolution-found-nothing)).

`RenderedTreeEditor::mapNodes()` applies one mapper to every node of a whole forest, rebuilding the copies down each branch, and is the idiom for anything beyond a single node.

## Example: Reading Time Listener

```php
#[AsEventListener(event: RenderedTreeFinalizationEvent::class)]
class ReadingTimeSubscriber
{
    private const WORDS_PER_MINUTE = 200;

    public function __construct(private readonly RenderedTreeEditor $editor)
    {
    }

    public function __invoke(RenderedTreeFinalizationEvent $event): void
    {
        $event->replaceTree($this->editor->mapNodes($event->tree(), function (RenderedElement $element): RenderedElement {
            $content = $element->properties['content'] ?? null;
            if (!\is_string($content)) {
                return $element;
            }

            $wordCount = str_word_count(strip_tags($content));

            return $element->withProperty('readingTimeMinutes', (int) ceil($wordCount / self::WORDS_PER_MINUTE));
        }));
    }
}
```

The listener writes a property and returns each node, so it changes no structure and stays mode-independent. In SKELETON the `content` property is absent, the mapper returns the node untouched, and the skeleton tree is identical to the full one.

Symfony reads `#[AsEventListener]` only on an **autoconfigured** service definition, so the attribute above registers nothing unless your `services.xml` carries `<defaults autoconfigure="true"/>` (or the definition sets `autoconfigure` itself). Without it the class is registered as an ordinary service and never called, and `priority` on it is inert for the same reason.

## Cache Context in Subscribers

Subscribers can add cache tags or disable caching via `$event->cacheContext`:

```php
// Add invalidation tags for external data
$event->cacheContext->addTags(['my-plugin-weather-' . $location]);

// Disable caching entirely (use sparingly)
$event->cacheContext->disable();
```

## Priorities

Core reserves no priority band. Priority only orders your listener against other extensions' listeners on the same event; every core step already runs after the preparation event and before the finalization one. Omit `priority` unless you are sequencing against another plugin.
