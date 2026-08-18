# Listener

Event-driven extension points for the content hydration lifecycle. Listeners modify `$event->elements` (the only mutable property) before and after data loading.

## Guides

- [docs/custom-listeners.md](docs/custom-listeners.md) - The plugin-facing guide to writing a hydration-lifecycle listener.

## Execution Order

Core ships no listener on either event. `ContentPipeline::load()` (module root) calls its preparation and finishing steps directly, in this order:

**Before hydration**, after `PreContentHydrationEvent` is dispatched:
1. Virtual-root wrap — wraps roots with a temporary container for layout-level context (`Layout/Scaffolding/VirtualRootWrapper`)
2. Placeholder resolution — resolves `{{variable}}` placeholders from the specification
3. Redistribute expansion — expands `redistribute: true` into broadcast providers
4. Partial prune — prunes the tree when `targetElementId` is specified (`Output/PartialRenderer`)

**After hydration**, before `PostHydrationEvent` is dispatched:
1. Virtual-root unwrap — removes the virtual root wrapper
2. Partial extract — extracts the target subtree for the response

So a `PreContentHydrationEvent` listener always sees the raw loaded tree, and a `PostHydrationEvent` listener always sees the finished one — at any priority.

## Priorities

There are no reserved bands. Priority only orders extension listeners against each other on the same event.

Until this became true, the documented bands were not merely unenforced but *inverted*: every core listener was registered at priority 0 (the `#[AsEventListener(priority: …)]` attributes never took effect, because these services were not autoconfigured), so a plugin listener at any priority above 0 ran BEFORE all of core and only a negative priority ran after. A plugin written to the old `>= 6000` / `< 1000` guidance got the opposite of what it intended. Both bands are gone; re-check any listener whose priority was chosen to sit before or after a core step.
