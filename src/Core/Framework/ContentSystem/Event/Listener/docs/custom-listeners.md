# Custom Event Listeners

The plugin-facing authoring guide for a rendering-lifecycle listener: the two events, where each one sits in the pipeline, and the element API a listener works against.

Listeners modify elements before or after rendering: computing derived values, transforming structure, resolving custom placeholders.

| Event                         | When                                               | Purpose                                  |
|-------------------------------|----------------------------------------------------|------------------------------------------|
| `ContentTreePreparationEvent` | Before every pipeline step and before data loading | Modify layout tree, resolve placeholders |
| `RenderedTreeFinalizationEvent` | After data loading and every step that shapes the tree | Enrich data, transform property values |

`ContentPipeline::load()` calls its own preparation and finishing steps directly rather than through these events, so the tree a listener sees does not depend on its priority. A `ContentTreePreparationEvent` listener sees the raw loaded layout, before placeholder resolution, the virtual-root wrap, the partial prune, the duplicate-element-id check, the wiring validation, the redistribute derivation and the render step. A `RenderedTreeFinalizationEvent` listener sees the finished rendered tree, after the virtual-root unwrap and the partial extract, and before the pipeline's second duplicate-element-id check, which judges the tree the listener handed back.

The two carry the tree in the model of their own position, and each exposes one way to put a changed tree back:

- `ContentTreePreparationEvent::tree()` — `list<StoredElement>`; a replacement goes back through `replaceTree()`, because a stored element is immutable and an edit produces new instances
- `RenderedTreeFinalizationEvent::tree()` — `list<RenderedElement>`; a replacement goes back through `replaceTree()`, because a rendered element is immutable too

Both expose the same remaining properties, all readonly:

- `layout` — `LayoutReference` exposing `id`, `name`, `version` of the rendered layout
- `specification` — `RenderingSpecification`
- `salesChannelContext` — `SalesChannelContext`
- `cacheContext` — `RenderingCacheContext`, for cache tag management (readonly reference, but methods mutate state)

Neither event exposes `RenderingMode`, and both are dispatched at the same position in both modes. That is deliberate: a listener's structural output must not depend on the rendering mode. Property values are empty in SKELETON and populated in FULL, so deriving structure, slots or style from a property value produces a tree that differs between a cached skeleton and the later full response, which breaks their composition. Mode remains observable indirectly (the per-format route name on the specification's request, property emptiness, loader effects on the cache context); the bar is a contract, not something the event shape can enforce.

### What a finalization listener may change

The tree a listener hands back through `replaceTree()` is what the response carries, and the pipeline checks it for a repeated element id before building anything from it. Only one edit is constrained:

| Edit | Result |
|------|--------|
| Rewrite property values | Supported, the whole point of the event |
| Remove an element or a subtree | Supported |
| Reorder elements or slot children | Supported |
| Add an element with a new id | Supported |
| Duplicate an existing element id | Fails the render, `CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID` (500) |

Element ids are a rendered-model contract, not bookkeeping: partial extraction addresses by id, the storefront emits `data-element-id`, and the decomposed format's `assignments` are keyed by it. The pipeline rejects a repeated id twice — once over the pre-prune stored forest before the render step, and once over the forest this event hands back — so a stored forest carrying one (a raw-SQL or migration write, or a preparation listener) fails just as a listener's duplicate does. Structural validity is otherwise the listener's responsibility; nothing repairs a tree a listener hands back.

`RenderedTreeEditor::mapNodes()` is the whole-tree edit idiom: it visits every existing node exactly once and replaces it with exactly one node, so the idiom itself neither mints nor drops nodes. That guarantee is about cardinality only. The mapper's signature is `callable(RenderedElement): RenderedElement` and whatever it returns is what lands in the tree, so nothing stops it returning an element whose id already exists elsewhere in the forest, or one carrying extra slot children. Using the idiom therefore does not discharge the id obligation — that stays with the listener, as above.

Placeholder resolution runs in FULL mode only, and it runs before this event, so a listener that introduces a `{{token}}` resolves it itself either way rather than expecting the pipeline to.

## Working with RenderedElement

`RenderedElement` is the tree node a `RenderedTreeFinalizationEvent` listener works against (a `ContentTreePreparationEvent` listener works against `StoredElement` instead). It is `final readonly`, so every edit returns a new instance:

| Member                                            | Purpose                                                       |
|---------------------------------------------------|---------------------------------------------------------------|
| `$id`                                             | Element ID, readonly                                          |
| `$component`                                      | Component type identifier, readonly                           |
| `$properties`                                     | The flat property map, readonly `array<string, mixed>`        |
| `$slots`                                          | Named child slots, readonly `array<string, list<RenderedElement>>` |
| `$style`                                          | `ElementStyle`, readonly                                      |
| `withProperty(string $key, mixed $value): self`   | Copy with one property set                                    |
| `withProperties(array $properties): self`         | Copy with the whole property map replaced                     |
| `withSlots(array $slots): self`                   | Copy with the slot map replaced                               |

A `null` property value is a present property holding null, which is how a lookup that ran and found nothing differs from one that never wrote at all. Use `array_key_exists()` on `$properties` when that distinction matters.

`RenderedTreeEditor::mapNodes(array $tree, callable $mapper): array` applies one mapper to every node of a whole forest, rebuilding the copies down each branch, and is the idiom for anything beyond a single node.

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

The listener writes a property and returns each node, so it changes no structure and stays mode-independent: in SKELETON the `content` property is absent, the mapper returns the node untouched, and the skeleton tree is identical to the full one.
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

Core reserves no priority band. Priority only orders your listener against other extensions' listeners on the same event; every core step already runs after the preparation event and before the finalization one. Omit `priority` unless you are sequencing against another plugin.

> **If you wrote a listener against the old bands, re-check it.** The `>= 6000` / `< 6000 and >= 1000` / `< 1000 and >= 0` contract documented here never worked, and it was *inverted*: core's listeners were all registered at priority 0, because their `#[AsEventListener(priority: …)]` attributes were never processed — those services were not autoconfigured. An autoconfigured plugin service's attribute, on the other hand, is processed, so a plugin listener at `6000`, meaning "before core", did run before core — but so did one at `1`, and so did one at `500` that meant "after core". Only a negative priority ran after core. Core no longer occupies either event at all, so both bands are meaningless now; a priority chosen to sit before or after a core step should be removed.
