# Custom Style Options

The plugin- and app-facing guide to registering a new style option.

Style options are universal presentation attributes — alignment, span, spacing, display — that can be set per breakpoint on **every** element, regardless of its type. Unlike an element type property, an option declares nothing about which elements it applies to: every registered option is valid on every element. Plugins and apps register options by placing YAML files in a style-options directory; the Administration decides where each control is shown from the option's `adminUI` hints.

The declaration's own file format is documented in [option-yaml.md](option-yaml.md).

## Registration

| Source | Directory                                | Name             | Customizable                          |
|--------|------------------------------------------|------------------|---------------------------------------|
| Plugin | `Resources/content-system/style-options` | kebab-case filename | No (fixed convention directory)    |
| App    | `Resources/content-system/style-options` | kebab-case filename | No                                 |

The compiler pass discovers plugin and bundle YAML automatically; app YAML is validated and persisted on install/update. No service registration needed. Unlike element types, the directory is fixed for plugins too, so there is no per-plugin override hook.

## Name Resolution

An option name is the **Store-API wire key** taken directly from the kebab-case filename — there is no source prefix and no directory nesting. `Resources/content-system/style-options/col-span.yaml` registers the option `col-span`. Names are flat and globally unique across core, bundles, plugins, and apps.

**Rules:**
- One option per YAML file
- Filenames must be kebab-case and start with a letter: `[a-z][a-z0-9]*(-[a-z0-9]+)*` (`YamlStyleOptionLoader::NAME_PATTERN`). An all-numeric name would coerce to an int array key on read and could never round-trip
- Both `.yaml` and `.yml` extensions accepted

## Collision Detection

Option names must be globally unique across core, bundles, plugins, and apps. Duplicates are detected at:
- **Load time** by the registry when aggregating loaders (core / bundle / plugin)
- **Persist time** by `StyleOptionCollisionDetector` when syncing app options to the database (also checks inactive app options). The `UNIQUE KEY` on `app_content_system_style_option.name` is the authoritative guard.

## App Lifecycle

App activation state is read live, not denormalized onto the option rows. `DatabaseStyleOptionLoader` joins `app` and filters `WHERE app.active = 1`, so deactivating an app drops its options from that query with no extra write. `ContentSystemStyleOptionLifecycleHandler::deactivate()` invalidates the cached registry immediately, so the `app.active = 1` filter takes effect on the next request rather than lagging until a later install/update. Options are persisted on app install/update by `ContentSystemStyleOptionLifecycleHandler` and cascade-deleted with the app.

## Validation Posture

Writing an element `style` is strict: an unknown option, an unknown breakpoint, or a value that violates the option's `type` / `enum` / `range` / `maxLength` is rejected (`HTTP 400`). Reading is registry-free: an option whose plugin or app has since been removed rides through verbatim in the served `style` so an old layout still renders, mirroring the element-type system's unknown-`component` handling. Re-saving that layout is rejected until the orphaned option is cleared, so a normal edit round-trip no longer auto-clears it.

## Discoverability

A registered option appears in `GET /api/_info/content-system-style-options.json` and, folded under a `styleOptions` key, in `GET /api/_info/content-system-element-types.json`. The Administration reads either to render controls from the option's `adminUI` hints. See [introspection.md](introspection.md).

Reference: [Layout/Element/Style/README.md](../README.md), `Layout/Element/Style/Definitions/` (5 core option examples)
