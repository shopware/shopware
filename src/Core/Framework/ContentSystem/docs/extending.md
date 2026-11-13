# Extending the Content System

| Extension Point           | Purpose                                                                |
|---------------------------|------------------------------------------------------------------------|
| **Element Types**         | New content components with declared properties and slots — authored per [Layout/Type/docs/custom-types.md](../Layout/Type/docs/custom-types.md) |
| **Style Options**         | New universal per-breakpoint presentation attributes for every element — authored per [Layout/Element/Style/docs/custom-options.md](../Layout/Element/Style/docs/custom-options.md) |
| **Binding Specifications** | Pre-validated data wirings for an element type, applied in one action — authored per [Binding/docs/custom-specifications.md](../Binding/docs/custom-specifications.md) |
| **Specification Sources** | New URL patterns, entity types — authored per [Adapter/docs/custom-sources.md](../Adapter/docs/custom-sources.md) |
| **Data Loaders**          | External APIs, calculations, aggregated data (with cache control) — authored per [Hydration/DataLoader/docs/custom-loaders.md](../Hydration/DataLoader/docs/custom-loaders.md) |
| **Event Listeners**       | Modify layout structure, enrich data, transform properties, cache tags — authored per [Event/Listener/docs/custom-listeners.md](../Event/Listener/docs/custom-listeners.md) |
| **Mapping Sources** | Curated typed sources through tagged plugin providers and intentional core overrides through the public guarded registry — [source providers](../Mapping/docs/source-providers.md), [plugin overrides](../Mapping/docs/plugin-sources.md) |

## Administration translations

Element type, preset and style option declarations retain their English text for introspection.
Use optional explicit Administration snippet keys to translate the text shown in Experience Studio.
For example:

```yaml
meta:
  label: "Image"
  labelSnippet: "my-extension.elements.image.label"
  description: "A responsive image."
properties:
  loading:
    type: string
    title: "Loading"
    titleSnippet: "my-extension.elements.image.loading"
    description: "Image loading strategy."
    adminUI:
      helpText: "Choose how the image loads."
      helpTextSnippet: "my-extension.elements.image.loadingHelp"
      props:
        options:
          - value: "lazy"
            label: "Lazy"
            labelSnippet: "my-extension.elements.image.loadingLazy"
```

Element metadata accepts `labelSnippet` and `descriptionSnippet`; properties, including nested properties, accept `titleSnippet` and `descriptionSnippet`.
Presets accept `nameSnippet` and `descriptionSnippet` next to `name` and `description`.
Style options accept `labelSnippet` and `descriptionSnippet` inside `adminUI`.
For element and style controls, `adminUI.helpTextSnippet`, `adminUI.props.placeholderSnippet`, and `labelSnippet` and `descriptionSnippet` on `adminUI.props.options` translate the corresponding text.
Only supported interface fields are translated; enum values, identifiers, defaults, preset payloads and agent metadata retain their original meaning.
Existing `adminUI.helpText` values that are snippet keys continue to work.

Provide the referenced keys through the usual Administration snippets for the core, plugin or app, under a namespace owned by the extension.
The editor falls back to the authored English text when a key is missing or has no translation.
Declarations without references continue to work unchanged.

## Layout presets

Preset declarations place display metadata next to the `layout` shorthand at the top level.
For example:

```yaml
name: "Text block"
description: "A ready-made rich-text block."
icon: "regular-align-left"
illustration: "content-text"
category: "content"
priority: 100
layout:
  - component: Sw:Content:Text
```

`icon` selects the Meteor icon, while optional `illustration` selects an SVG filename without its extension.
The picker resolves illustrations from the Administration's `static/img/experience-studio/element-icons/` directory and falls back to `icon` if the SVG fails to load.
Omit `illustration` or set it to `null` to show only the Meteor icon.
No Administration mapping entry or icon prefix is required.

`category` is an optional non-blank string; an omitted or null category places the preset under Other.
Use `layout`, `content`, or `commerce` to group presets under the existing translated category headings, in that order.
Custom category headings use the `sw-experience-studio.detail.elementPicker.categoryHeadlines.<category>` snippet, as element categories do.
`priority` is an optional integer defaulting to `0`, with higher values appearing first within a category.
Negative values are allowed; equal priorities sort by display name, then by the unique preset id.
Use gaps such as `100`, `200`, and `300` to leave space for other presets.

Both YAML declarations and app declarations persisted as JSON preserve these fields.
`GET /api/_info/content-system-layout-presets.json` always returns `category`, `priority`, and `illustration` alongside each preset's metadata and compiled `payload`.
Existing declarations can omit these optional authoring fields.
