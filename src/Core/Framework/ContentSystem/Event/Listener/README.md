# Listener

Event-driven extension points for the content hydration lifecycle. A listener replaces the stored forest via `ContentTreePreparationEvent::replaceTree()` before data loading, and modifies `$event->elements` after it.

## Guides

- [docs/custom-listeners.md](docs/custom-listeners.md) - The plugin-facing guide to writing a hydration-lifecycle listener.

## Execution Order

Core ships no listener on either event. `ContentPipeline::load()` (module root) calls its preparation and finishing steps directly, in this order:

**Before hydration**, after `ContentTreePreparationEvent` is dispatched:
1. Placeholder resolution — resolves `{{variable}}` placeholders from the specification on the stored tree, in FULL mode only (`Layout/Scaffolding/StoredTreePreparer`)
2. Virtual-root wrap — wraps the stored roots with a temporary container for layout-level context (`Layout/Scaffolding/VirtualRootWrapper`)
3. Redistribute expansion — expands `redistribute: true` into broadcast providers, on the stored tree
4. Lowering — takes the stored tree onto the `ContentElement` model the remaining steps speak (`Layout/Element/ContentElementLowering`)
5. Partial prune — prunes the tree when `targetElementId` is specified (`Output/PartialRenderer`)

**After hydration**, before `PostHydrationEvent` is dispatched:
1. Virtual-root unwrap — removes the virtual root wrapper, on the lowered tree
2. Partial extract — extracts the target subtree for the response

So a `ContentTreePreparationEvent` listener always sees the raw loaded tree, and a `PostHydrationEvent` listener always sees the finished one — at any priority.

## Priorities

There are no reserved bands. Priority only orders extension listeners against each other on the same event.

Until this became true, the documented bands were not merely unenforced but *inverted*: every core listener was registered at priority 0 (the `#[AsEventListener(priority: …)]` attributes never took effect, because these services were not autoconfigured), so a plugin listener at any priority above 0 ran BEFORE all of core and only a negative priority ran after. A plugin written to the old `>= 6000` / `< 1000` guidance got the opposite of what it intended. Both bands are gone; re-check any listener whose priority was chosen to sit before or after a core step.
