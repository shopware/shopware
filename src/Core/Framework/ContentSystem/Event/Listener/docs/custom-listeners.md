# Custom Event Listeners

The plugin-facing authoring guide for a hydration-lifecycle listener: the two events, the element API a listener works against, and the priority ranges an extension may claim.

Listeners modify elements before or after hydration—computing derived values, transforming structure, resolving custom placeholders.

| Event                      | When             | Purpose                                  |
|----------------------------|------------------|------------------------------------------|
| `PreContentHydrationEvent` | Before hydration | Modify layout tree, resolve placeholders |
| `PostHydrationEvent`       | After hydration  | Enrich data, transform structure         |

Both events expose the same properties. Only `elements` is mutable:

- `elements` — `list<ContentElement>`, mutable
- `layout` — `LayoutReference` exposing `id`, `name`, `version` of the rendered layout (readonly)
- `specification` — `RenderingSpecification` (readonly)
- `mode` — `RenderingMode` (readonly)
- `salesChannelContext` — `SalesChannelContext` (readonly)
- `cacheContext` — `RenderingCacheContext`, for cache tag management (readonly reference, but methods mutate state)

## Working with ContentElement

`ContentElement` is the tree node in the layout. In event listeners, access and modify element data through these methods:

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
#[AsEventListener(event: PostHydrationEvent::class, priority: 500)]
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

## Cache Context in Subscribers

Subscribers can add cache tags or disable caching via `$event->cacheContext`:

```php
// Add invalidation tags for external data
$event->cacheContext->addTags(['my-plugin-weather-' . $location]);

// Disable caching entirely (use sparingly)
$event->cacheContext->disable();
```

## Priority Guidelines

| Range                | Usage                              |
|----------------------|------------------------------------|
| `>= 6000`            | Run BEFORE core processing         |
| `< 6000 and >= 1000` | **Reserved for core** - do not use |
| `< 1000 and >= 0`    | Run AFTER core processing          |
| `< 0`                | Run after all other subscribers    |

Reference: `Event/Listener/PreHydration/PlaceholderResolutionSubscriber.php`
