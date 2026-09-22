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

**`properties`** (optional): Each property declares its type (`string`, `boolean`, `integer`, `number`, or a FQCN for hydrated data). Optional fields: `required`, `translatable` (string only), `enum` (primitives only), `default`, `title`, `description`, `adminUI`, `mappable`, `resolvedBy` (reference properties only — the resolvedBy shorthand, see [Custom Binding Specifications](../../../Binding/docs/custom-specifications.md)).

The default-specification synthesizer runs on every type file, whether or not it declares a `bindings:` key, so a misused `resolvedBy` — for example on a primitive property — fails app install and `manifest:validate` outright.

### `mappable`

`mappable: true` lets a layout author replace the property's authored value with a path into the page's entity data — on a category layout, pointing a text property at `category.name`. It defaults to **false**, so a property is never mappable unless you say so, and that default is the switch for the distinction below.

```yaml
properties:
  text:
    type: string
    mappable: true
```

**Do not set it on a property the element needs in order to work.** A property you fill yourself — through `dataRequirements`, a binding specification, or root-ambient context the page supplies — is your contract with the element, not a choice to hand the author. `Sw:Product:Listing`'s `listing` property is the reference case: the page loads the listing once and delivers it as root-scoped context, the element cannot render without it, and it stays silent on `mappable`. Leaving the flag off is enough; `Validation/StoredMappingValidator` then rejects any write that tries to map it, so no client can map it behind your back.

A mapping is keyed by the destination property and carries the catalogued dotted path in `sourcePath`; ordinary and server-mirrored context wiring never carries `sourcePath`. Use the dedicated mapping mutations rather than writing this metadata in a client: the server derives cardinality and projection from the candidate catalogue.

If you do mark a property both mappable and self-filled, a mapping **wins**: `Output/Index/ValueOrigin` ranks `DeliveredContext` above `LoaderResolved`, and nothing warns you. That combination is only meaningful when overriding your loader is a feature you intend to offer — which is how `Sw:Media:Image.media` works, where mapping the category's image and picking one by hand are two ways to say the same thing.

Note what that means for a **required** reference with a `resolvedBy` storage key. Mapping it and filling the storage key are alternatives, so an author who maps leaves the key empty, and the diagnostics rule that would normally call an empty required loader input a defect (`UnfilledRequiredInput`) stands down for a mapped property. You get that for free; it is not something a type file opts into.

You do not declare which candidates fit, either. The introspection payload derives a `contextTypes` entry from your `type` — `single`, `collection`, or both for a union or a bare `object` — and the Administration filters the catalogue by it before anything else, so a `MediaCollection` property is never offered a single image. Declare the type accurately and that follows; there is no flag to set.

What a mappable property can be mapped TO is not yours to declare either, and you should not shape a property around the entity data you hope to see in it. The offer set comes from the mapping catalogue of whichever root source the layout is bound to, and the write gate admits a path only when the value it yields satisfies your declared type. Where the data exists but is shaped wrong — the gallery declares `MediaCollection`, and a product's `media` is a collection of image ASSIGNMENT records rather than of images — the catalogue entry carries a **projection** that reshapes it, and advertises the type your property ends up holding. So you declare the type your template needs and nothing else: bridging to it is the catalogue's problem, and an author never sees that it happened. See [Mapping/Projection](../../../Mapping/Projection/AbstractContentPropertyProjection.php) if you are the one writing the bridge.

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
