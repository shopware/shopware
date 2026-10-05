# Custom Specification Sources

Specification sources translate path patterns into rendering specifications via discrete steps. A blog plugin rendering posts at `/store-api/content/blog/{id}` implements a source that recognizes the `blog/` prefix and resolves the corresponding layout.

## Chain of Responsibility

Sources are tried in priority order (highest first). `RenderingSpecificationResolver` iterates them with the `supports()` bool check; the first source where it returns true handles the request via `RenderingSpecificationFactory`. A source never signals "not mine" by returning null.

| Priority                     | Behavior                                      |
|------------------------------|-----------------------------------------------|
| Higher values                | Tried first                                   |
| `supports()` returns `false` | Skip to next source                           |
| `supports()` returns `true`  | Handle request via stepped resolution         |
| Throw exception              | Only when your source should handle but fails |

## Example: Blog Post Source

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

**Required: an assignable-entity definition.** A `content_system.entity_specification_source` source must receive an `AbstractContentLayoutAssignableDefinition` subclass (here `BlogPostContentLayoutDefinition`) as a constructor argument. At container build, `ContentLayoutAssignableCompilerPass` introspects each tagged source's arguments for such a definition and derives the entity type from its `getContentLayoutEntityType()`. A source with no assignable-definition argument fails compilation with `missingAssignableDefinition`. Define the assignment entity and its definition alongside the source — see `Adapter/Entity/AbstractContentLayoutAssignableDefinition`.

Reference: `src/Core/Content/Product/Aggregate/ProductContentLayout/ProductSpecificationSource.php` (and `ProductContentLayoutDefinition.php` in the same directory)

## Assignment-Free Resolution (Preview Support)

The steps above resolve a layout from a path for the Store API. To also let the Admin preview and diagnose actions work against an entity that has no assignment yet, an entity-backed source overrides more methods: `supportsEntityType()` (match the source's content-layout entity type), `resolveSpecificationDataForEntity()` (build the spec data from the entity id directly, with no assignment lookup), and `providedRootContext()` (the root-ambient context the source supplies to the layout; default `[]`). See [Context flows only between adjacent elements](../../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements). The diagnose route (`POST /api/_action/content-system/layout/diagnose`) resolves its request `rootSource` through `Adapter/RootSourceRegistry`. It calls `providedRootContext()` on the matching source to run its binding-resolvability checks. A source that leaves it at the default exposes no root context, so those checks have nothing to bind against. An assignable entity type registered this way also appears in `GET /api/_info/content-system-entity-types.json`. See [introspection.md](introspection.md).
