# Rendering

Rendering is the path from a loaded stored forest to the rendered forest that a response carries.

## The render validates the whole stored forest in every mode

The render runs a final check on the finished forest. Wiring validation and the duplicate-id check run on the pre-prune forest, after the preparation event and before data resolution. After the finalization event, `ContentPipeline::load()` checks the finished forest again for a repeated id. Both checks therefore apply to each tree that a listener hands back. A defect in a discarded subtree still fails the render. A tree stored past the DAL fails hard at render time. The write gate must accept no wiring shape that the planner rejects.

| Defect | FULL | SKELETON |
|---|---|---|
| Wiring defect | fails | fails |
| Duplicate id | fails | fails |
| Data-dependent failure | fails | does not fail |

Why: Checking only the surviving subtree lets one defect fail one request and pass another. The write gate runs only on DAL writes, so a tree stored past the DAL or returned by a listener would render unchecked. Partial extraction, the storefront's `data-element-id` and the decomposed format's `assignments` address an element by its id, so a repeated id would address two elements.

Not chosen: Checking the post-prune subtree only, trusting the tree that a listener returns, or the write gate as the final check.

In code:

- `ContentPipeline::load()` checks `prePruneForest` and the finished forest for a repeated id.
- `WiringPlanner::plan()` validates `prePruneForest`.
- `ContentRouteRenderingTest` pins that a wiring defect fails the render in FULL and in SKELETON.
- See [pipeline-steps.md](../pipeline-steps.md).

## Structure is a function independent of rendering mode

The skeleton runs the full render path. The skeleton never takes a prune-to-target shortcut. A cached skeleton and the full response are therefore structurally identical and differ in data alone. A listener never derives structure, slots or style from a property value.

| Step on the render path | FULL | SKELETON |
|---|---|---|
| Placeholder substitution | on | off |
| Data resolution | on | off |

Why: A client caches the skeleton and fills it with data later, so a structural difference from the full response breaks that fill. No runtime guard can compare the two within one request, so the identity must hold by construction, not by test.

In code:

- `StoredTreePreparer::prepare()` gates placeholder substitution on `RenderingMode::FULL`.
- `ElementLowering::lower()` gates data resolution on the same mode.
- `RenderedTreeFactory` walks both modes in one traversal.
- `ContentRouteRenderingTest` pins that the skeleton equals the full response minus the properties.
- See [Rendering/README.md](../../Rendering/README.md).

## Rendering stages are direct calls handing each other typed immutable results

Rendering stages run as direct calls in an order stated once at the call site, not as priority-ordered listeners. Each stage hands the next stage a typed immutable record instead of writing into a shared mutable element. The two tree-replacement events, one over the `StoredElement` tree and one over the `RenderedElement` tree, stay the only extension points. No internal stage moves onto those two events.

Why: The order that the old priority bands declared never applied. A typed record carries what only its producing stage established, which a tree-in, tree-out listener cannot pass on. The accepted price is that no core listener exists on either event.

In code:

- `ContentPipeline::load()` dispatches only `ContentTreePreparationEvent` and `RenderedTreeFinalizationEvent`.
- See [Event/README.md](../../Event/README.md).

## The two tree-replacement events fire at fixed points

Neither event exposes the rendering mode. The preparation event fires first, before placeholder substitution. Placeholder substitution therefore still reaches any placeholder that the listener adds. The finalization event fires after the finishing steps, in both modes. The pipeline checks each tree that an event hands back, under the [final check](#the-render-validates-the-whole-stored-forest-in-every-mode).

Why: A later event would show a listener content already substituted or pruned.

Exceptions: A returned tree that names an unregistered element type is [registry drift](wire-contract.md#structural-malformation-and-registry-drift-are-different-cases-on-read), on a returned tree as on a stored one.

In code:

- `ContentPipeline::load()` dispatches both events without a `RenderingMode`.
- It reads the tree that `replaceTree()` set.
- See [custom-listeners.md](../../Event/Listener/docs/custom-listeners.md).

## A template reads a rendered element through a fixed small surface

An element template reads `id`, `component`, `properties`, `slots` and `style`, and nothing else. The template computes a derived value such as a class name from the stored style. The module never stores that derived value. The partial reads a request-scoped toggle, such as the preview marker, from the request. A request-scoped toggle never enters the content model. A component takes its data through the property map.

Why: `RenderedElement` must keep every field that a template can read. A stored toggle is saved with the layout and applies to every request.

In code:

- `RenderedElement` has the five fields only.
- `_element.html.twig` derives the class string from `style.values` and hands the property map to the component.
- `StoredElement` has no class field.
- See [stored-and-rendered.md](../stored-and-rendered.md).

## A null in the rendered map means a resolution found nothing

An unfulfilled key stays absent from the rendered map. A null in the rendered map means that a resolution ran and found nothing. A resolution that found nothing is a loader's `notFound()` or a context delivery that resolved to nothing. That null overwrites the stored value in the working map. A parent therefore hands its children what it renders. An undelivered consumer key and an authored null stay absent. No per-element signal or log entry records why an element rendered null, and the module defers observability for render-time degradation.

Why: A stale stored value left by a loader that found nothing is a degraded output that looks valid, which the [fail-fast rule](failure-and-loss.md#a-component-throws-where-it-meets-invalid-data-and-nothing-degrades-silently) bans. A reader of the rendered map tells found-nothing from never-ran.

In code:

- `RenderedElementFactory::create()` overwrites stored values with loader values.
- It reads the loader values with `array_key_exists`.
- It skips an authored null.
- `ContextDeliveryResolver` overwrites stored values with loader values in the working map that it builds for `ContextDistributor::distribute()`.
- See [listener-api.md](../../Event/Listener/docs/listener-api.md).

```text
stored    Sw:Text: headline = null (authored), product = null, data requirement on product
loader    product found nothing: ['product' => null]
rendered  ['product' => null], no headline key
stored    Sw:Tile: data requirement on product, required consumer category, nothing delivered
rendered  ['product' => null], no category key
```
