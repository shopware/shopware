# Extending the ContentSystem

Plugins extend the ContentSystem through five mechanisms.

## Table of Contents

1. [Extension Model](#extension-model)
2. [Custom Element Types](#custom-element-types)
3. [Custom Style Options](#custom-style-options)
4. [Custom Specification Sources](#custom-specification-sources)
5. [Custom Data Loaders](#custom-data-loaders)
6. [Event Listeners](#event-listeners)
7. [Service Tags](#service-tag-reference)
8. [Type Reference](#type-reference)

## Extension Model

| Extension Point           | Purpose                                                                |
|---------------------------|------------------------------------------------------------------------|
| **Element Types**         | New content components with declared properties and slots              |
| **Style Options**         | New universal per-breakpoint presentation attributes for every element |
| **Specification Sources** | New URL patterns, entity types                                         |
| **Data Loaders**          | External APIs, calculations, aggregated data (with cache control)      |
| **Event Listeners**       | Modify layout structure, enrich data, transform properties, cache tags |

---

## Custom Element Types

Element types define what content components exist, their properties, and their slots. They are the schema for what a hydrated element looks like in the API response. Plugins and apps register types by placing YAML files in a types directory.

### Registration

| Source | Directory                        | Name Prefix       | Customizable                                 |
|--------|----------------------------------|-------------------|----------------------------------------------|
| Plugin | `Resources/content-system/types` | Plugin class name | Yes, via `Plugin::getContentTypeDirectory()` |
| App    | `Resources/content-system/types` | App name          | No                                           |

The compiler pass discovers YAML files automatically. No service registration needed.

### Name Resolution

Type names are derived from the file path relative to the types directory. Directory segments and filenames are converted from kebab-case to PascalCase and joined with colons. The source prefix is prepended automatically.

**Example:** Plugin `AcmeStore` with file `Resources/content-system/types/product/quick-view.yaml` produces type name `AcmeStore:Product:QuickView`.

**Rules:**
- One type per YAML file
- Filenames and directories must be kebab-case: `[a-z0-9]+(-[a-z0-9]+)*`
- Both `.yaml` and `.yml` extensions accepted
- `meta.name` in YAML is ignored; names come exclusively from file paths

### YAML Structure

```yaml
meta:
  label: "Quick View"
  description: "Inline product preview overlay"
  icon: "regular-eye"
  category: "product"
  copilot:
    summary: "Shows a quick product preview"
    hints:
      - "Use inside product listings"

properties:
  productId:
    type: string
    required: true
    title: "Product ID"
    description: "UUID of the product to preview"
  showPrice:
    type: boolean
    default: true
    title: "Show Price"

slots:
  - name: actions
    description: "Action buttons below product info"
    maxElements: 3
    allowList:
      - "Sw:Content:Button"
      - "AcmeStore:AddToCart"
```

**`meta`** (required): `label`, `description` are required. `icon`, `category`, `copilot` are optional.

**`properties`** (optional): Each property declares its type (`string`, `boolean`, `integer`, `number`, or a FQCN for hydrated data). Optional fields: `required`, `translatable` (string only), `enum` (primitives only), `default`, `title`, `description`, `adminUI`.

**`slots`** (optional): Each slot has a `name`. Optional: `maxElements` (cap on child count), `allowList` (restrict allowed child component types), `description`.

### Collision Detection

Type names must be globally unique across core, bundles, plugins, and apps. Duplicates are detected at:
- **Compile time** by the registry when aggregating loaders
- **Persist time** by `ElementTypeCollisionDetector` when syncing app types to the database (also checks inactive app types). This is a best-effort check with a TOCTOU window: the registry snapshot is read before the DB write, so concurrent app installs proposing the same name can both pass. The `UNIQUE KEY` on `app_content_system_element_type.name` is the authoritative guard.

### App Lifecycle

App activation state is read live, not denormalized onto the element type rows. `DatabaseTypeLoader` joins `app` and filters `WHERE app.active = 1`, so deactivating an app drops its element types from that query with no extra write, though the cached registry keeps serving them until its next invalidation (the persister on a later app install/update). Element types are persisted on app install/update by `ContentSystemElementTypeLifecycleHandler` and cascade-deleted with the app.

Reference: `Layout/Type/README.md`, `Layout/Type/Definitions/` (5 core type examples)

### Discoverability

A registered type appears in `GET /api/_info/content-system-element-types.json`, which the Administration reads to offer the type (with its property and slot schema) in the layout editor. See `ADMINISTRATION.md`.

---

## Custom Style Options

Style options are universal presentation attributes — alignment, span, spacing, display — that can be set per breakpoint on **every** element, regardless of its type. Unlike an element type property, an option declares nothing about which elements it applies to: every registered option is valid on every element. Plugins and apps register options by placing YAML files in a style-options directory; the Administration decides where each control is shown from the option's `adminUI` hints.

### Registration

| Source | Directory                                | Name             | Customizable                          |
|--------|------------------------------------------|------------------|---------------------------------------|
| Plugin | `Resources/content-system/style-options` | kebab-case filename | No (fixed convention directory)    |
| App    | `Resources/content-system/style-options` | kebab-case filename | No                                 |

The compiler pass discovers plugin and bundle YAML automatically; app YAML is validated and persisted on install/update. No service registration needed. Unlike element types, the directory is fixed for plugins too, so there is no per-plugin override hook.

### Name Resolution

An option name is the **Store-API wire key** taken directly from the kebab-case filename — there is no source prefix and no directory nesting. `Resources/content-system/style-options/col-span.yaml` registers the option `col-span`. Names are flat and globally unique across core, bundles, plugins, and apps.

**Rules:**
- One option per YAML file
- Filenames must be kebab-case: `[a-z0-9]+(-[a-z0-9]+)*`
- Both `.yaml` and `.yml` extensions accepted

### YAML Structure

The declaration is **flat** — there is no `meta:` wrapper (this differs from element-type YAML):

```yaml
type: integer
range:
  min: 1
  max: 12
adminUI:
  component: "number"
  label: "Column Span"
  description: "How many grid columns the element spans."
```

A string-enum option instead:

```yaml
type: string
default: "auto"
enum:
  - "auto"
  - "start"
  - "center"
  - "end"
adminUI:
  component: "select"
  label: "Align Self"
```

- **`type`** (required): one of `string`, `integer`, `number`, `boolean`.
- **`enum`** (optional, primitives): the allowed value set.
- **`range`** (optional, `integer` / `number`): `min` and/or `max` bounds.
- **`maxLength`** (optional, declarable on `string` only): caps the stored string. A `string` or `number` with no `maxLength` declared is still capped at 255, so a client cannot store an unbounded value (including a long numeric string); `integer` and `boolean` are unaffected.
- **`default`** (optional): advisory only — an introspection and Admin pre-fill hint. It is **not** seeded into stored elements and **not** applied at serve time, so an element's `style` stays omitted when empty.
- **`adminUI`** (optional): an opaque block passed through verbatim to the Administration; the backend never interprets it.

There is deliberately no `pattern` / regex: an app-supplied regex compiled from untrusted data and run on every write is a ReDoS vector, so strings are bounded by `maxLength` instead.

### Breakpoints

Values are set per breakpoint. The breakpoint key set is the fixed framework primitive `xs, sm, md, lg, xl, xxl`; it is not extensible. Each breakpoint is optional, so a responsive option may set only some of them. On an element, the stored shape is `option => breakpoint => value`:

```json
{ "col-span": { "md": 6, "lg": 4 }, "display": { "xs": false } }
```

### Collision Detection

Option names must be globally unique across core, bundles, plugins, and apps. Duplicates are detected at:
- **Load time** by the registry when aggregating loaders (core / bundle / plugin)
- **Persist time** by `StyleOptionCollisionDetector` when syncing app options to the database (also checks inactive app options). The `UNIQUE KEY` on `app_content_system_style_option.name` is the authoritative guard.

### App Lifecycle

App activation state is read live, not denormalized onto the option rows. `DatabaseStyleOptionLoader` joins `app` and filters `WHERE app.active = 1`, so deactivating an app drops its options from that query with no extra write. `ContentSystemStyleOptionLifecycleHandler::deactivate()` invalidates the cached registry immediately, so the `app.active = 1` filter takes effect on the next request rather than lagging until a later install/update. Options are persisted on app install/update by `ContentSystemStyleOptionLifecycleHandler` and cascade-deleted with the app.

### Validation Posture

Writing an element `style` is strict: an unknown option, an unknown breakpoint, or a value that violates the option's `type` / `enum` / `range` / `maxLength` is rejected (`HTTP 400`). Reading is registry-free: an option whose plugin or app has since been removed rides through verbatim in the served `style` so an old layout still renders, mirroring the element-type system's unknown-`component` handling. Re-saving that layout is rejected until the orphaned option is cleared, so a normal edit round-trip no longer auto-clears it.

### Discoverability

A registered option appears in `GET /api/_info/content-system-style-options.json` and, folded under a `styleOptions` key, in `GET /api/_info/content-system-element-types.json`. The Administration reads either to render controls from the option's `adminUI` hints. See `ADMINISTRATION.md`.

Reference: `Layout/Element/Style/README.md`, `Layout/Element/Style/Definitions/` (7 core option examples)

---

## Custom Specification Sources

Specification sources translate path patterns into rendering specifications via discrete steps. A blog plugin rendering posts at `/store-api/content/blog/{id}` implements a source that recognizes the `blog/` prefix and resolves the corresponding layout.

### Chain of Responsibility

Sources are tried in priority order (highest first). The first source where `supports()` returns true handles the request via `RenderingSpecificationFactory`.

| Priority                     | Behavior                                      |
|------------------------------|-----------------------------------------------|
| Higher values                | Tried first                                   |
| `supports()` returns `false` | Skip to next source                           |
| `supports()` returns `true`  | Handle request via stepped resolution         |
| Throw exception              | Only when your source should handle but fails |

### Example: Blog Post Source

```php
final class BlogPostSpecificationSource extends AbstractSpecificationSource
{
    public function __construct(
        private readonly EntityRepository $blogLayoutAssignmentRepository,
        // Must be an AbstractContentLayoutAssignableDefinition subclass (see note below)
        private readonly BlogPostContentLayoutDefinition $definition,
    ) {}

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    { /* Return true if path starts with 'blog/' */ }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    { /* Extract post ID from path; query blog_post_content_layout with sales channel fallback; return layout ID */ }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    { /* Build data requirements and placeholder values from the path and request — no layout assignment lookup needed; return SpecificationData */ }

    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    { /* Extract optional elementId from request query parameters */ }

    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    { /* Return cache tags for the blog post entity */ }
}
```

**Service registration:**

```xml
<!-- The assignable-entity definition the compiler pass derives the entity type from -->
<service id="MyPlugin\ContentSystem\BlogPostContentLayoutDefinition">
    <tag name="shopware.entity.definition"/>
</service>

<service id="MyPlugin\ContentSystem\BlogPostSpecificationSource">
    <argument type="service" id="blog_post_content_layout.repository"/>
    <argument type="service" id="MyPlugin\ContentSystem\BlogPostContentLayoutDefinition"/>
    <!-- Higher priority = tried first -->
    <tag name="content_system.entity_specification_source" priority="100"/>
</service>
```

**Required: an assignable-entity definition.** A `content_system.entity_specification_source` source must receive an `AbstractContentLayoutAssignableDefinition` subclass (here `BlogPostContentLayoutDefinition`) as a constructor argument. At container build, `ContentLayoutAssignableCompilerPass` introspects each tagged source's arguments for such a definition and derives the entity type from its `getContentLayoutEntityType()`; a source with no assignable-definition argument fails compilation with `missingAssignableDefinition`. Define the assignment entity and its definition alongside the source — see `Adapter/Entity/AbstractContentLayoutAssignableDefinition`.

Reference: `src/Core/Content/Product/Aggregate/ProductContentLayout/ProductSpecificationSource.php` (and `ProductContentLayoutDefinition.php` in the same directory)

### Assignment-Free Resolution (Preview Support)

The steps above resolve a layout from a path for the Store API. To also let the Admin preview and diagnose actions work against an entity that has no assignment yet, an entity-backed source overrides three more methods: `supportsEntityType(string $entityType): bool` (match the source's content-layout entity type), `resolveSpecificationDataForEntity(string $entityId, Request, SalesChannelContext): SpecificationData` (build the spec data from the entity id directly, with no assignment lookup), and `providedRootContext(Context $context): list<ProvidedContext>` (the root-ambient context the source supplies to the layout's top-level elements; default `[]`). The diagnose route (`POST /api/_action/content-system/layout/diagnose`) resolves its request `rootSource` through `Adapter/RootSourceRegistry`, which calls `providedRootContext()` on the matching source to run its binding-resolvability checks — a source that leaves it at the default exposes no root context, so those checks have nothing to bind against. An assignable entity type registered this way also appears in `GET /api/_info/content-system-entity-types.json`. See `ADMINISTRATION.md`.

---

## Custom Data Loaders

Data loaders fetch external data—APIs, computed values, aggregations. The built-in `entity` loader handles Shopware entities; other built-in loaders handle known data structures like product listing, navigation, language, currency, payment method, and shipping method.

A data loader consists of three classes:

| Component  | Base Class                                  | Service Tag                        | Purpose                |
|------------|---------------------------------------------|------------------------------------|------------------------|
| Config     | `AbstractContentDataLoaderConfig`           | (none)                             | Hold loader parameters |
| Serializer | `AbstractContentDataLoaderConfigSerializer` | `content_system.config_serializer` | Encode/decode config   |
| Loader     | `AbstractContentDataLoader`                 | `content_system.data_loader`       | Fetch the data         |

Define array shapes with `@phpstan-type ConfigData array{field?: type}` in the Config class, then import with `@phpstan-import-type ConfigData from ConfigClass` in the Serializer. Annotate `encode()` with `@return ConfigData` for type-safe serialization.

### Example: Weather Data Loader

The `source` value (`weather`) links all three components.

**Config:**

```php
final readonly class WeatherLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function __construct(
        public string $location,
        public string $units = 'metric',
    ) {}
}
```

**Serializer:**

```php
final class WeatherLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    { return 'weather'; /* Must match loader's getRequirementType() */ }

    public function decode(array $data): AbstractContentDataLoaderConfig
    { /* Convert array to WeatherLoaderConfig */ }

    public function encode(AbstractContentDataLoaderConfig $config): array
    { /* Convert WeatherLoaderConfig to array */ }
}
```

**Loader:**

```php
/**
 * @extends AbstractContentDataLoader<WeatherStruct>
 */
final class WeatherLoader extends AbstractContentDataLoader
{
    public function __construct(private readonly WeatherApiClient $weatherClient) {}

    public static function getRequirementType(): string
    { return 'weather'; /* Must match serializer's getSource() */ }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        $config = $requirement->config;
        \assert($config instanceof WeatherLoaderConfig);

        $weather = $this->weatherClient->fetch($config->location);
        if ($weather === null) {
            return ContentDataLoaderResult::notFound();
        }
        // External API - cannot track for invalidation
        return ContentDataLoaderResult::uncacheable($weather);
    }
}
```

**Service registration:**

```xml
<service id="MyPlugin\ContentSystem\Weather\WeatherLoaderConfigSerializer">
    <tag name="content_system.config_serializer"/>
</service>

<service id="MyPlugin\ContentSystem\Weather\WeatherLoader">
    <argument type="service" id="MyPlugin\Service\WeatherApiClient"/>
    <tag name="content_system.data_loader"/>
</service>
```

### Cache Awareness

All data loaders must return `ContentDataLoaderResult` to indicate cache behavior:

| Factory Method            | When to Use                                               |
|---------------------------|-----------------------------------------------------------|
| `notFound()`              | Data not found, page remains cacheable                    |
| `cached($data, ...$tags)` | Data with invalidation tags (e.g., `'product-' . $id`)    |
| `cachedExternally($data)` | Data loaded via delegated route that handles its own tags |
| `uncacheable($data)`      | External APIs or data that cannot be cache-tracked        |

If any loader returns uncacheable data, the entire page becomes uncacheable.

For entity-based data, provide cache tags that match Shopware's existing invalidation patterns:

```php
// Use tag patterns matching Shopware's cache invalidation system:
// product → 'product-{id}', category → 'category-route-{id}',
// landing_page → 'landing-page-route-{id}', cms_page → 'cms-page-{id}'
return ContentDataLoaderResult::cached($entity, 'product-' . $entityId);
```

Reference: `Hydration/DataLoader/EntityLoader/`

### Discoverability

A registered loader's `source` value and the capabilities it produces (via `producibleTypes()`) appear in `GET /api/_info/content-system-data-loader-types.json`, which the Administration reads to offer the data source when authoring `data_requirements`. Wildcard loaders (`entity`, `entity_collection`) override `producibleTypes()`/`resolveProducedType()` to enumerate the live definition registry. See `ADMINISTRATION.md`.

---

## Event Listeners

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

### Working with ContentElement

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

### Example: Reading Time Listener

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

### Cache Context in Subscribers

Subscribers can add cache tags or disable caching via `$event->cacheContext`:

```php
// Add invalidation tags for external data
$event->cacheContext->addTags(['my-plugin-weather-' . $location]);

// Disable caching entirely (use sparingly)
$event->cacheContext->disable();
```

### Priority Guidelines

| Range                | Usage                              |
|----------------------|------------------------------------|
| `>= 6000`            | Run BEFORE core processing         |
| `< 6000 and >= 1000` | **Reserved for core** - do not use |
| `< 1000 and >= 0`    | Run AFTER core processing          |
| `< 0`                | Run after all other subscribers    |

Reference: `Event/Listener/PreHydration/PlaceholderResolutionSubscriber.php`

---

## Service Tag Reference

| Tag                                   | Index Method           | Attributes                                     |
|---------------------------------------|------------------------|------------------------------------------------|
| `content_system.entity_specification_source` | N/A             | `priority` (optional, default 0)               |
| `content_system.specification_source` | `section` attribute    | `section` (required, e.g. `header` / `footer`) |
| `content_system.data_loader`          | `getRequirementType()` | None                                           |
| `content_system.config_serializer`    | `getSource()`          | None                                           |
| `content_system.section_resolver`     | `section` attribute    | `section` (required, e.g. `main` / `header` / `footer`) |

Full DI configuration: `src/Core/Framework/DependencyInjection/content-system.xml`

---

## Type Reference

Key types extension developers encounter when working with the ContentSystem:

### Base Classes (extend these)

| Class                                       | Purpose                     |
|---------------------------------------------|-----------------------------|
| `AbstractSpecificationSource`               | Custom specification source |
| `AbstractContentDataLoader`                 | Custom data loader          |
| `AbstractContentDataLoaderConfig`           | Loader configuration DTO    |
| `AbstractContentDataLoaderConfigSerializer` | Config encode/decode        |

### Result / Value Objects

| Class                     | Purpose                                                                                |
|---------------------------|----------------------------------------------------------------------------------------|
| `ContentDataLoaderResult` | Loader return value with cache info                                                    |
| `LoaderTypeCapability`    | One type a loader can produce: `producedType`, `configTemplate`, `requiredConfigKeys`, `genericParameters`; returned by `producibleTypes()` (construct directly when overriding it) |
| `SpecificationData`       | Return type of `resolveSpecificationData()` (bundles data requirements + placeholders) |
| `PlaceholderValues`       | Immutable placeholder map, created via `PlaceholderValues::from(array $values)`        |
| `RenderingSpecification`  | Data requirements, placeholders, request, target element, cache tags                   |
| `ResolvedContentLayout`   | Resolver output: layout ID plus the `RenderingSpecification`                           |
| `LayoutReference`         | Immutable layout identity: `id`, `name`, `version`                                     |
| `RenderableLayout`        | Loaded layout handed to the pipeline: a `LayoutReference` plus its element list        |

### Enums

| Enum             | Values                     | Purpose                         |
|------------------|----------------------------|---------------------------------|
| `RenderingMode`  | `FULL`, `SKELETON`         | Controls whether hydration runs |
| `ContentSection` | `MAIN`, `HEADER`, `FOOTER` | Identifies content section      |

### Event Classes

| Class                      | Purpose                     |
|----------------------------|-----------------------------|
| `PreContentHydrationEvent` | Dispatched before hydration |
| `PostHydrationEvent`       | Dispatched after hydration  |

### Layout / Response

| Class                    | Purpose                                         |
|--------------------------|-------------------------------------------------|
| `ContentElement`         | Tree node: properties, slots, data requirements |
| `RenderingCacheContext`  | Cache tag collection + disable flag             |
| `ContentSystemException` | Exception class with error codes                |

All classes above are in the `Shopware\Core\Framework\ContentSystem` namespace (or subnamespaces).
