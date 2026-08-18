# Custom Event Listeners

The plugin-facing authoring guide for a hydration-lifecycle listener: the two events, where each one sits in the pipeline, and the element API a listener works against.

Listeners modify elements before or after hydration—computing derived values, transforming structure, resolving custom placeholders.

| Event                         | When                                               | Purpose                                  |
|-------------------------------|----------------------------------------------------|------------------------------------------|
| `ContentTreePreparationEvent` | Before every pipeline step and before data loading | Modify layout tree, resolve placeholders |
| `PostHydrationEvent`          | After data loading and every pipeline step         | Enrich data, transform structure         |

`ContentPipeline::load()` calls its own preparation and finishing steps directly rather than through these events, so the tree a listener sees does not depend on its priority. A `ContentTreePreparationEvent` listener sees the raw loaded layout, before placeholder resolution, the virtual-root wrap, the lowering onto `ContentElement`, redistribute expansion and the partial prune. A `PostHydrationEvent` listener sees the finished tree, after the virtual-root unwrap and the partial extract.

The two carry the tree in the model of their own position, and each exposes one way to put a changed tree back:

- `ContentTreePreparationEvent::tree()` — `list<StoredElement>`; a replacement goes back through `replaceTree()`, because a stored element is immutable and an edit produces new instances
- `PostHydrationEvent::$elements` — `list<ContentElement>`, mutable in place

Both expose the same remaining properties, all readonly:

- `layout` — `LayoutReference` exposing `id`, `name`, `version` of the rendered layout
- `specification` — `RenderingSpecification`
- `salesChannelContext` — `SalesChannelContext`
- `cacheContext` — `RenderingCacheContext`, for cache tag management (readonly reference, but methods mutate state)

`PostHydrationEvent` additionally exposes `mode` — `RenderingMode`. `ContentTreePreparationEvent` does not: it is dispatched at the same position in both modes.

Placeholder resolution runs in FULL mode only, and it runs after this event either way, so a listener that introduces a `{{token}}` resolves it itself rather than expecting the pipeline to.

## Working with ContentElement

`ContentElement` is the tree node a `PostHydrationEvent` listener works against (a `ContentTreePreparationEvent` listener works against the immutable `StoredElement` instead). Access and modify element data through these methods:

| Method                                         | Purpose                                          |
|------------------------------------------------|--------------------------------------------------|
| `getProperty(string $key): mixed`              | Get property value (returns null if not found)   |
| `setProperty(string $key, mixed $value): void` | Set a property value                             |
| `hasProperty(string $key): bool`               | Check if property exists                         |
| `getProperties(): array`                       | Get all properties                               |
| `getId(): string`                              | Element ID                                       |
| `getComponent(): string`                       | Component type identifier                        |
| `getSlots(): array`                            | Named child slots (`array<string, SlotContent>`) |
| `allSlotElements(): Generator`                 | Generator yielding all direct child elements     |
| `hasSlots(): bool`                             | Whether element has child slots                  |

## Example: Reading Time Listener

```php
#[AsEventListener(event: PostHydrationEvent::class)]
class ReadingTimeSubscriber
{
    private const WORDS_PER_MINUTE = 200;

    public function __invoke(PostHydrationEvent $event): void
    {
        foreach ($event->elements as $element) {
            $content = $element->getProperty('content');
            if (!\is_string($content)) {
                continue;
            }
            $wordCount = str_word_count(strip_tags($content));
            $element->setProperty('readingTimeMinutes', (int) ceil($wordCount / self::WORDS_PER_MINUTE));
        }
    }
}
```

Symfony reads `#[AsEventListener]` only on an **autoconfigured** service definition, so the attribute above registers nothing unless your `services.xml` carries `<defaults autoconfigure="true"/>` (or the definition sets `autoconfigure` itself). Without it the class is registered as an ordinary service and never called — and `priority` on it is inert for the same reason.

## Cache Context in Subscribers

Subscribers can add cache tags or disable caching via `$event->cacheContext`:

```php
// Add invalidation tags for external data
$event->cacheContext->addTags(['my-plugin-weather-' . $location]);

// Disable caching entirely (use sparingly)
$event->cacheContext->disable();
```

## Priorities

Core reserves no priority band. Priority only orders your listener against other extensions' listeners on the same event; every core step already runs after the pre-hydration event and before the post-hydration one. Omit `priority` unless you are sequencing against another plugin.

> **If you wrote a listener against the old bands, re-check it.** The `>= 6000` / `< 6000 and >= 1000` / `< 1000 and >= 0` contract documented here never worked, and it was *inverted*: core's listeners were all registered at priority 0, because their `#[AsEventListener(priority: …)]` attributes were never processed — those services were not autoconfigured. An autoconfigured plugin service's attribute, on the other hand, is processed, so a plugin listener at `6000`, meaning "before core", did run before core — but so did one at `1`, and so did one at `500` that meant "after core". Only a negative priority ran after core. Core no longer occupies the event at all, so both bands are meaningless now; a priority chosen to sit before or after a core step should be removed.
