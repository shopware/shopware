# Custom Element Types

The plugin- and app-facing guide to registering a new element type.

Element types define what content components exist, their properties, and their slots. They are the schema for what a hydrated element looks like in the API response. Plugins and apps register types by placing YAML files in a types directory.

## Registration

| Source | Directory                        | Name Prefix       | Customizable                                 |
|--------|----------------------------------|-------------------|----------------------------------------------|
| Plugin | `Resources/content-system/types` | Plugin class name | Yes, via `Plugin::getContentTypeDirectory()` |
| App    | `Resources/content-system/types` | App name          | No                                           |

The compiler pass discovers YAML files automatically. No service registration needed.

## Name Resolution

Type names are derived from the file path relative to the types directory. Directory segments and filenames are converted from kebab-case to PascalCase and joined with colons. The source prefix is prepended automatically.

**Example:** Plugin `AcmeStore` with file `Resources/content-system/types/product/quick-view.yaml` produces type name `AcmeStore:Product:QuickView`.

**Rules:**
- One type per YAML file
- Filenames and directories must be kebab-case: `[a-z0-9]+(-[a-z0-9]+)*`
- Both `.yaml` and `.yml` extensions accepted
- `meta.name` in YAML is ignored; names come exclusively from file paths

## YAML Structure

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

**`properties`** (optional): Each property declares its type (`string`, `boolean`, `integer`, `number`, or a FQCN for hydrated data). Optional fields: `required`, `translatable` (string only), `enum` (primitives only), `default`, `title`, `description`, `adminUI`, `resolvedBy` (reference properties only — the resolvedBy shorthand, see [Custom Binding Specifications](../../../Binding/docs/custom-specifications.md)).

The default-specification synthesizer runs on every type file, whether or not it declares a `bindings:` key, so a misused `resolvedBy` — for example on a primitive property — fails app install and `manifest:validate` outright.

**`slots`** (optional): Each slot has a `name`. Optional: `maxElements` (cap on child count), `allowList` (restrict allowed child component types), `description`.

**`bindings`** (optional): Inline binding specifications for this type. See [Custom Binding Specifications](../../../Binding/docs/custom-specifications.md).

## Collision Detection

Type names must be globally unique across core, bundles, plugins, and apps. Duplicates are detected at:
- **Compile time** by the registry when aggregating loaders
- **Persist time** by `ElementTypeCollisionDetector` when syncing app types to the database (also checks inactive app types). This is a best-effort check with a TOCTOU window: the registry snapshot is read before the DB write, so concurrent app installs proposing the same name can both pass. The `UNIQUE KEY` on `app_content_system_element_type.name` is the authoritative guard.

## App Lifecycle

App activation state is read live, not denormalized onto the element type rows. `DatabaseTypeLoader` joins `app` and filters `WHERE app.active = 1`, so deactivating an app drops its element types from that query with no extra write, though the cached registry keeps serving them until its next invalidation (the persister on a later app install/update). Element types are persisted on app install/update by `ContentSystemElementTypeLifecycleHandler` and cascade-deleted with the app.

Reference: [Layout/Type/README.md](../README.md), `Layout/Type/Definitions/` (5 core type examples)

## Discoverability

A registered type appears in `GET /api/_info/content-system-element-types.json`, which the Administration reads to offer the type (with its property and slot schema) in the layout editor. See [introspection.md](introspection.md).
