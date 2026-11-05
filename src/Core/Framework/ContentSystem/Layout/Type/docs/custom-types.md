# Custom Element Types

Element types define what content components exist, their properties, and their slots. They are the schema for what a hydrated element looks like in the API response. Plugins and apps register types by placing YAML files in a types directory.

## Registration

| Source | Directory                        | Name Prefix       | Customizable                                 |
|--------|----------------------------------|-------------------|----------------------------------------------|
| Plugin | `Resources/content-system/types` | Plugin name | Yes, via `Plugin::getContentTypeDirectory()` |
| App    | `Resources/content-system/types` | App name          | No                                           |

The compiler pass discovers YAML files automatically. No service registration needed.

## Name Resolution

Type names are derived from the file path relative to the types directory. Directory segments and filenames are converted from kebab-case to PascalCase and joined with colons. The source prefix is prepended automatically: `Sw` for core and bundles, the short `Plugin::getName()` value (not the FQCN) for plugins, the app name for apps.

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
  priority: 100
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

**`meta`** (required): `label`, `description` are required.
`icon`, `illustration`, `category`, `priority`, `copilot` are optional.

`meta.icon` selects a Meteor icon such as `regular-image`, used in the sidebar tree and as the picker fallback.
The optional `meta.illustration` selects an SVG filename without its extension, such as `media-image`.
The picker loads it from `src/Administration/Resources/app/administration/static/img/experience-studio/element-icons/`.
An omitted or null illustration shows the Meteor icon; an image that fails to load also falls back to that icon.
The two fields are independent and require no Administration mapping entry or icon prefix.
Preset declarations support the same fields at the top level.

```yaml
meta:
  icon: "regular-image"
  illustration: "media-image"
```

`meta.priority` is an integer controlling the element picker order within its category.
Higher values appear first; an omitted priority defaults to `0`, and negative values are allowed.
Equal priorities are ordered alphabetically by label, then by the unique type name.
Use gaps such as `100`, `200`, and `300` to leave room for other elements.
Priority does not change category order.
Presets declare their own top-level `category` and `priority` fields; see [Layout presets](../../../docs/extending.md#layout-presets).

**`properties`** (optional): Each property declares its type: a primitive (`string`, `boolean`, `integer`, `number`; the set is `PropertyType::PRIMITIVE_TYPES`) or any other value, read as a `class-string<Struct>` FQCN for hydrated data. Optional fields: `required`, `translatable` (`type: string` only), `enum` (primitives only, a list whose values match the declared type; enforced by `TypedEnumValidator`), `default` (primitives only, a value matching the declared type; enforced by `TypedDefaultValidator`), `title`, `description`, `adminUI`, `mappable`, `inlineMappable`, `defaultMapping`, `resolvedBy` (reference properties only; the resolvedBy shorthand, see [Custom Binding Specifications](../../../Binding/docs/custom-specifications.md)). A property shown as a `radio-panel` whose type is not `string` declares its values in `enum`; without one, a click on one of its options throws an error in the Administration and emits no value.

The default-specification synthesizer runs on every type file, whether or not it declares a `bindings:` key. A misused `resolvedBy`, for example on a primitive property, therefore fails app install and `manifest:validate` outright.

### `mappable`

`mappable: true` lets a layout author fill the property from a registered data source — for example, a text property from `category.name` or an entity property from the whole root product. It defaults to **false**, so a property is never mappable unless you say so, and that default is the switch for the distinction below.

```yaml
properties:
  text:
    type: string
    mappable: true
```

**Do not set it on a property the element needs in order to work.** A property you fill yourself — through `dataRequirements`, a binding specification, or root-ambient context the page supplies — is your contract with the element, not a choice to hand the author. `Sw:Product:Listing`'s `listing` property is the reference case: the page loads the listing once and delivers it as root-scoped context, the element cannot render without it, and it stays silent on `mappable`. Leaving the flag off is enough; `Validation/StoredMappingValidator` then rejects any write that tries to map it, so no client can map it behind your back.

Use the dedicated [mapping mutations](../../../Api/docs/mutation.md) to select a source; the server derives cardinality and checks admission. `defaultMapping` is a typed source declaration seeded on new scaffolds, for example `{type: root, id: product}`. It supplies initial source wiring rather than a second property type. `resolvedBy` supplies the loader-backed alternative after unmapping. See [data mapping](../../../Mapping/README.md#stored-mappings-and-rendering) for seeding scope and preservation of authored choices.

If you mark a property both mappable and self-filled, a delivered mapping value, including explicit null, takes precedence over authored or loader-resolved content. `Rendering/RenderedElementFactory` applies that precedence. See [data mapping](../../../Mapping/README.md#stored-mappings-and-rendering) for fallback, omission, and provider-error rules. Static diagnostics report mapping admission faults. Offer this combination only when overriding the loader is intentional, as with mapping `Sw:Media:Image.media` or choosing an image manually.

Note what that means for a **required** reference with a `resolvedBy` storage key. Mapping it and filling the storage key are alternatives, so an author who maps leaves the key empty, and the diagnostics rule that would normally call an empty required loader input a defect (`UnfilledRequiredInput`) stands down for a mapped property. You get that for free; it is not something a type file opts into.

You do not declare candidate cardinality separately. Introspection derives `contextTypes` from the declared type; [introspection.md](introspection.md) describes union-member rules. Declare the type accurately; there is no flag to set.

What a mappable property can be mapped to is determined by the mapping catalogue of the layout's root source. The write gate admits a candidate only when its effective value type satisfies your declared property type. Where the source data has a different shape — the gallery declares `MediaCollection`, while a product's `media` holds image assignment records — the provider reshapes it before delivery and advertises `MediaCollection`. Declare the type your template needs; the provider owns the conversion.

### `inlineMappable`

`inlineMappable: true` allows `{{map:candidate.path}}` tokens in authored text. It requires exactly `string` and cannot be combined with `mappable`. Use it for sanitized rich text, as with `Sw:Content:Text`; a plain-text output component does not support this rendering contract. See [inline mapping](../../../Mapping/README.md#stored-mappings-and-rendering) for eligibility and resource limits, and [rich-text rendering](../../../Mapping/README.md#rich-text-rendering) for escaping and sanitization.

**`slots`** (optional): Each slot has a `name`. Optional: `maxElements` (cap on child count), `allowList` (restrict allowed child component types), `description`. A slot name PHP casts to an integer, such as `12`, is unusable: a stored element rejects an integer slot key (`invalidMapKey`, see [stored-model.md](../../../docs/principles/stored-model.md#every-map-key-is-a-string-and-the-module-rejects-an-integer-castable-key-never-coerces-it)), so no child can be stored under it.

**`bindings`** (optional): Inline binding specifications for this type. See [Custom Binding Specifications](../../../Binding/docs/custom-specifications.md).

## Collision Detection

Type names must be globally unique across core, bundles, plugins, and apps. Duplicates are detected at:
- **Compile time** by the registry when aggregating loaders
- **Persist time** by `ElementTypeCollisionDetector` when syncing app types to the database (also checks inactive app types). This is a best-effort check with a TOCTOU window: the registry snapshot is read before the DB write, so concurrent app installs proposing the same name can both pass. The `UNIQUE KEY` on `app_content_system_element_type.name` is the authoritative guard.

## App Lifecycle

App activation state is read live, not denormalized onto the element type rows. `DatabaseTypeLoader` joins `app` and filters `WHERE app.active = 1`. Deactivating an app therefore drops its element types from that query with no extra write. Yet the cached registry keeps serving them until its next invalidation (the persister on a later app install/update). Element types are persisted on app install/update by `ContentSystemElementTypeLifecycleHandler` and cascade-deleted with the app.

Reference: [Layout/Type/README.md](../README.md), `Layout/Type/Definitions/`

## Discoverability

A registered type appears in `GET /api/_info/content-system-element-types.json`, which the Administration reads to offer the type (with its property and slot schema) in the layout editor. See [introspection.md](introspection.md).
