# MT Tabs Major Feature Flag Migration Design

## Context

Issue `shopware/shopware#14822` asks to migrate Administration tab usage from the legacy `sw-tabs` and `sw-tabs-item` slot API to Meteor's `mt-tabs` `items` API. The current Administration still has many `<sw-tabs>` and `<sw-tabs-item>` consumers, and `sw-tabs` currently acts as a compatibility wrapper that switches between `sw-tabs-deprecated` and `mt-tabs` behind `V6_8_0_0`.

The migration must be clean for the next major release while remaining fully backward compatible before the major feature flag is active.

## Goals

- Convert core Administration consumers to `mt-tabs` with explicit `items` definitions for the major behavior.
- Keep legacy `sw-tabs`, `sw-tabs-deprecated`, `sw-tabs-item`, legacy slots, old props, refs, and extension behavior working while `V6_8_0_0` is inactive.
- Hide breaking removals and changed template structure behind `V6_8_0_0`.
- Preserve `position-identifier` based tab extensions through the `mt-tabs` wrapper.
- Add linting coverage that prevents new unguarded `sw-tabs` usage.
- Cover both legacy and major behavior with Jest tests.

## Non-Goals

- Do not remove legacy tab components outside the active major feature flag path.
- Do not change unrelated Administration layout or styling.
- Do not refactor unrelated module state, routing, or test setup.
- Do not rely on the existing open PR implementation as the source of truth.

## Architecture

The migration keeps the existing public contract intact while adding a major-only rendering path.

When `V6_8_0_0` is inactive, existing templates continue to render the old `sw-tabs` markup and slot content. This preserves third-party overrides that depend on Twig blocks, component names, slots, refs, props such as `position-identifier`, `small`, `default-item`, and events such as `new-item-active`.

When `V6_8_0_0` is active, migrated consumers render `mt-tabs` directly with `:items`. Components that need dynamic labels, routes, disabled states, or active-tab state expose dedicated computed properties or data properties for the tab items. Trivial static tab lists may use inline `:items` only when doing so keeps the template clearer than adding component code.

The `mt-tabs` wrapper remains responsible for integrating Shopware tab extension entries by `positionIdentifier`. It merges core items with extension-provided items and keeps route activation behavior centralized.

## Component Migration Pattern

Each consumer follows the same shape where a breaking template change is needed:

```twig
<template v-if="majorFeatureFlagActive">
    <mt-tabs
        position-identifier="example-tabs"
        :items="tabItems"
        @new-item-active="onNewItemActive"
    />

    <component-content v-if="activeTab === 'base'" />
</template>

<template v-else>
    <sw-tabs position-identifier="example-tabs">
        <sw-tabs-item name="base">
            {{ $tc('example.base') }}
        </sw-tabs-item>

        <template #content="{ active }">
            <component-content v-if="active === 'base'" />
        </template>
    </sw-tabs>
</template>
```

Consumers that need template branches expose a small computed value based on `Shopware.Feature.isActive('V6_8_0_0')`. If a consumer already delegates flag handling through `sw-tabs`, only the parts that become major-only need explicit branches.

## Data Flow

Tab item data flows from component state into `mt-tabs` through `items`. A tab item contains at least `label` and `name`, and may include route or click handling when the previous `sw-tabs-item` used `route`.

`mt-tabs` or the Shopware wrapper emits the active item change through the same event name expected by migrated consumers. Consumers that previously depended on `#content="{ active }"` store the active item locally and render the correct content next to the tabs in the major branch. Legacy branches continue to receive `active` from the old slot API.

Tab extensions flow through `positionIdentifier` into the central tabs store. The `mt-tabs` wrapper merges those extension items after the core items so extension behavior remains available in the major branch.

## Backward Compatibility

This change treats every Administration component name, slot, Twig block, prop, ref, event, and CSS hook as public until the major flag is active.

Legacy components remain registered while `V6_8_0_0` is inactive. Deprecated APIs should receive `@major-deprecated` metadata or Administration template comments that point developers to `mt-tabs :items`. Breaking removals are allowed only inside code paths that are active for the next major version.

Existing `sw-tabs` usages in third-party extensions must continue to work in the inactive-flag state. Core tests should verify that representative legacy consumers still render and emit as before with the flag inactive.

## Linting

The Administration deprecation lint rules should reject new `sw-tabs` and `sw-tabs-item` usage unless the usage is part of an explicit `V6_8_0_0` compatibility branch. This keeps the BC path available for existing code while preventing new consumers from being added to the deprecated API.

The rule should point developers to `mt-tabs :items` and include valid test cases for migrated `mt-tabs` usage, invalid test cases for unguarded `sw-tabs` usage, and valid compatibility examples where legacy markup is guarded by the inactive major flag branch.

## Error Handling

The migration should avoid runtime errors for empty or extension-only tab sets. `mt-tabs` receives an empty array when no core or extension items exist. Consumers with route-based tabs must keep their existing route navigation behavior, and route pushes should remain non-blocking.

If a migrated tab cannot be represented safely by static template data, it should use component computed state rather than codemod-style placeholders.

## Testing

Testing should include:

- `mt-tabs` wrapper tests for item rendering, extension merging, empty items, and route click behavior.
- Representative consumer tests with `V6_8_0_0` inactive to prove legacy `sw-tabs` behavior still works.
- Representative consumer tests with `V6_8_0_0` active to prove `mt-tabs` receives correct `items` and active content switches correctly.
- Regression checks that no core template remains with major-branch `<sw-tabs>` usage after migration.
- ESLint deprecation rule coverage so new unguarded legacy tab usage cannot be introduced.

Verification should run the Administration autofixers and relevant Jest tests after implementation.

## Rollout

Implement the migration in batches by Administration area, but keep each batch BC-safe with both flag states covered where behavior changes. Once all core consumers have a major branch using `mt-tabs`, the final major-only cleanup can remove legacy component dependencies from the active major path while leaving the inactive-flag path intact for compatibility.
