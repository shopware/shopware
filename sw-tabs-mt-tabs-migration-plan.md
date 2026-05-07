# Migrate `sw-tabs` to `mt-tabs`

Issue: https://github.com/shopware/shopware/issues/14822

## Goal

Replace all legacy Administration usages of `<sw-tabs>` and `<sw-tabs-item>` with the Meteor Component Library tab API:

```twig
<mt-tabs
    :items="tabItems"
    :default-item="activeTab"
    @new-item-active="setActiveTab"
/>
```

After the migration, the Administration should no longer register or ship `sw-tabs`, `sw-tabs-deprecated`, or `sw-tabs-item`. The existing Shopware `mt-tabs` wrapper must stay because it is still needed for the Admin Extension SDK `ui.tabs` bridge.

## Confirmed Decisions

- Keep the Shopware `mt-tabs` wrapper to merge Admin Extension SDK tab items from `Shopware.Store.get('tabs')` by `positionIdentifier`.
- It is acceptable to replace custom warning tab indicators with the Meteor `badge: 'warning'` API where needed.

## Backward Compatibility Classification

This migration is a breaking Administration change according to the Shopware backward-compatibility guideline.

Relevant Administration BC rules:

- Removing `sw-tabs`, `sw-tabs-deprecated`, or `sw-tabs-item` is a removal of base components and is not backward compatible.
- Removing or changing the old default slot and `#content` slot API is a Vue slot removal/change and is not backward compatible.
- Changing `@new-item-active` from emitting a legacy tab item/component object to emitting a string tab name changes Vue event parameters and is not backward compatible for old consumers.
- Removing or changing legacy props such as `default-item`, `position-identifier`, `small`, `align-right`, `is-vertical`, `active-tab`, `route`, or `has-error` is a component API break.
- Removing root or functional selectors such as `.sw-tabs`, `.sw-tabs-item`, and `.sw-tabs__content` is not backward compatible for Administration styling overrides.
- Removing or renaming Twig blocks around tab markup is not backward compatible unless the deprecation workflow was followed.

Conclusion: the direct removal of the legacy tab API must be treated as a major-version change. It is acceptable for the `V6_8_0_0` major removal path, but it should not be released as an unguarded minor or patch change.

## Major Feature Flag Shape

For trunk development before the major release, the safe shape is a dual path:

- Keep the old `sw-tabs`, `sw-tabs-deprecated`, and `sw-tabs-item` components registered while `V6_8_0_0` is inactive.
- Add the new direct `mt-tabs :items="[...]"` implementation behind `V6_8_0_0`.
- Keep legacy template slots, legacy event payloads, legacy refs, and legacy selectors available on the inactive side of the flag.
- Only delete legacy components, legacy snippets, and legacy CSS when the major flag is removed for the 6.8 release line.

The core pattern for a migrated consumer should look like this while the major flag still exists:

```twig
<template v-if="useMeteorTabs">
    <mt-tabs
        position-identifier="sw-example-tabs"
        :items="tabItems"
        :default-item="activeTab"
        @new-item-active="setActiveTab"
    />

    <example-general-tab v-if="activeTab === 'general'" />
    <example-details-tab v-if="activeTab === 'details'" />
</template>

<template v-else>
    <sw-tabs
        position-identifier="sw-example-tabs"
        default-item="general"
        @new-item-active="setActiveTabFromLegacyItem"
    >
        <sw-tabs-item name="general">
            {{ $t('sw-example.tabs.general') }}
        </sw-tabs-item>

        <sw-tabs-item name="details">
            {{ $t('sw-example.tabs.details') }}
        </sw-tabs-item>

        <template #content="{ active }">
            <example-general-tab v-if="active === 'general'" />
            <example-details-tab v-if="active === 'details'" />
        </template>
    </sw-tabs>
</template>
```

The related component logic should keep old and new event payload handling separate:

```js
computed: {
    useMeteorTabs() {
        return Shopware.Feature.isActive('V6_8_0_0');
    },

    tabItems() {
        return [
            {
                label: this.$t('sw-example.tabs.general'),
                name: 'general',
            },
            {
                label: this.$t('sw-example.tabs.details'),
                name: 'details',
                badge: this.hasWarnings ? 'warning' : undefined,
                hasError: this.hasErrors,
            },
        ];
    },
},

methods: {
    setActiveTab(name) {
        this.activeTab = name;
    },

    setActiveTabFromLegacyItem(item) {
        this.activeTab = item.name;
    },
},
```

For route-backed tabs, the new item array should contain route navigation and should use route names as item names:

```js
computed: {
    tabItems() {
        const id = this.$route.params.id;

        return [
            {
                label: this.$t('sw-example.tabs.general'),
                name: 'sw.example.detail.general',
                onClick: () => this.$router.push({
                    name: 'sw.example.detail.general',
                    params: { id },
                }),
            },
        ];
    },
},
```

For shared wrapper components, prefer an additive API while the flag exists:

- Add optional `tabItems` props to wrapper components such as `sw-meteor-card` or `sw-meteor-page`.
- Keep the old `tabs` or `page-tabs` slot until the major cleanup.
- Render the new `tabItems` path when `V6_8_0_0` is active.
- Render the old slot path when `V6_8_0_0` is inactive.
- Add deprecation comments to old slots/props where they remain visible to extension developers.

This creates short-term duplication, but it keeps minor/patch releases compatible and makes the major cleanup mechanical: remove the `v-else` legacy branches, remove old props/slots, remove the old components, and delete legacy tests.

## Current Scope

The current checkout contains:

- 49 Administration Twig files with `<sw-tabs>` usages.
- 50 Administration Twig files with `<sw-tabs-item>` usages.
- Around 69 Administration specs that reference legacy tabs, legacy stubs, or legacy CSS selectors.

Important existing implementation points:

- `src/Administration/Resources/app/administration/src/app/component/meteor-wrapper/mt-tabs/index.ts`
- `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs/index.ts`
- `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs-deprecated/index.js`
- `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs-item/index.js`
- `src/Administration/Resources/app/administration/src/app/init/tabs.init.ts`
- `src/Administration/Resources/app/administration/src/app/store/tabs.store.ts`

## Target Component Contract

Use the Meteor `TabItem` model as the only tab item API:

```ts
type TabItem = {
    label: string;
    name: string;
    hasError?: boolean;
    disabled?: boolean;
    badge?: 'positive' | 'critical' | 'warning' | 'info';
    onClick?: (name: string) => void;
    hidden?: boolean;
};
```

`mt-tabs` props/events to rely on:

- `items`: required tab item array.
- `defaultItem`: active tab name on mount and when the default changes.
- `vertical`: replacement for legacy `is-vertical`.
- `small`: still exists in MCL but is deprecated upstream; do not add new usage unless preserving existing layout requires it.
- `new-item-active`: emits the active tab name as a string.

## Implementation Plan

### 1. Stabilize the `mt-tabs` Wrapper

Update the Shopware `mt-tabs` wrapper so it is the only Shopware-owned tab abstraction.

- Keep `positionIdentifier` support for Admin Extension SDK `ui.tabs`.
- Make `positionIdentifier` optional so simple tab usages do not need empty or artificial identifiers.
- Keep merging SDK tab items into `items` for matching `positionIdentifier` values.
- Ensure SDK tab item clicks continue to navigate to the generated extension route path.
- Keep the wrapper test coverage in `mt-tabs.spec.js` and extend it for optional `positionIdentifier`.

### 2. Define Migration Patterns

Convert each usage by behavior pattern instead of using a blind template replacement.

#### Route-backed Tabs

Legacy pattern:

```twig
<sw-tabs position-identifier="sw-import-export">
    <sw-tabs-item :route="{ name: 'sw.import.export.index.import' }">
        {{ $t('sw-import-export.page.importTab') }}
    </sw-tabs-item>
</sw-tabs>
```

Target pattern:

```twig
<mt-tabs
    position-identifier="sw-import-export"
    :items="tabItems"
    :default-item="$route.name"
/>
```

The component should provide `tabItems` in JS/TS, with each item using `onClick` to call `this.$router.push(route)`.

#### Local Content Tabs

Legacy pattern uses `#content="{ active }"` from `sw-tabs`.

Target pattern:

- Store active tab state in the parent component, for example `activeTab`.
- Pass `:default-item="activeTab"`.
- Update state with `@new-item-active="setActiveTab"`.
- Render tab content next to `<mt-tabs>` using `v-if="activeTab === '...'"` or `v-show` where component persistence is required.

#### Dynamic Tabs

For `v-for` tab items, move the mapped data into a computed `tabItems` array.

- Keep stable `name` values.
- Move dynamic classes only if they represent behavior. Pure CSS hooks should usually be removed or replaced with container-level selectors.
- Move `:has-error` to `hasError`.
- Move `:disabled` to `disabled`.

#### Conditional Tabs

For legacy `v-if` or `v-show` on `<sw-tabs-item>`, filter the computed `items` array.

- Do not leave hidden items in `items`, because `mt-tabs` can still select anything present in the list.
- If the currently active item disappears, reset to the first visible item or route to the closest valid route.

#### Warning/Error Tabs

- Map legacy `:has-error` to `hasError`.
- Map warning-only custom icon states to `badge: 'warning'`.
- Remove legacy inline icon markup inside tab labels because MCL labels are plain strings.

### 3. Migrate Core Wrapper Consumers First

These components currently abstract or forward legacy tab APIs and should be migrated before broad page work:

- `src/app/component/meteor/sw-meteor-page/sw-meteor-page.html.twig`
- `src/app/component/meteor/sw-meteor-page/index.ts`
- `src/app/component/meteor/sw-meteor-card/sw-meteor-card.html.twig`
- `src/app/component/meteor/sw-meteor-card/index.js`
- `src/app/component/extension-api/sw-extension-component-section/sw-extension-component-section.html.twig`
- `src/app/component/extension-api/sw-extension-component-section/index.ts`
- `src/app/component/form/sw-custom-field-set-renderer/sw-custom-field-set-renderer.html.twig`
- `src/app/component/form/sw-custom-field-set-renderer/index.js`

Special care:

- `sw-meteor-page` currently accepts `sw-tabs-item` through the `page-tabs` slot. Replace this with an `items`-based prop or a named slot contract that no longer requires `sw-tabs-item`.
- `sw-meteor-card` currently accepts `sw-tabs-item` in its `tabs` slot. Replace this with explicit `tabItems` or an equivalent items-based API.
- `sw-extension-component-section` can build `items` directly from `componentSection.props.tabs`.
- `sw-custom-field-set-renderer` uses `$refs.tabComponent.mountedComponent()` and `setActiveItem({ name })`; replace this with local active state and direct loading/reset logic.

### 4. Migrate Route-backed Pages

Start with the mostly mechanical route tab pages to establish the final pattern and reduce references quickly.

Representative files:

- `src/module/sw-import-export/page/sw-import-export/sw-import-export.html.twig`
- `src/module/sw-settings-search/page/sw-settings-search/sw-settings-search.html.twig`
- `src/module/sw-users-permissions/page/sw-users-permissions-role-detail/sw-users-permissions-role-detail.html.twig`
- `src/module/sw-settings-country/page/sw-settings-country-detail/sw-settings-country-detail.html.twig`
- `src/module/sw-flow/page/sw-flow-detail/sw-flow-detail.html.twig`

Implementation details:

- Add computed `tabItems` arrays to the corresponding component scripts.
- Use `$t(...)` for labels in computed items so language changes are reflected by reactivity.
- Use route names as item names where the tab is route-backed.
- Use `$router.push(...)` in `onClick`.

### 5. Migrate State-backed Content Tabs

These require component logic changes because their tab content is controlled by the old `#content` slot state.

Representative files:

- `src/module/sw-settings-search/component/sw-settings-search-searchable-content/sw-settings-search-searchable-content.html.twig`
- `src/module/sw-settings-tag/component/sw-settings-tag-detail-modal/sw-settings-tag-detail-modal.html.twig`
- `src/module/sw-order/component/sw-order-address-modal/sw-order-address-modal.html.twig`
- `src/module/sw-order/component/sw-order-new-customer-modal/sw-order-new-customer-modal.html.twig`
- `src/module/sw-order/component/sw-order-create-initial-modal/sw-order-create-initial-modal.html.twig`
- `src/app/component/media/sw-media-modal-folder-settings/sw-media-modal-folder-settings.html.twig`

Implementation details:

- Introduce or reuse an `activeTab` field.
- Replace legacy `@new-item-active="handler($event.name)"` with `@new-item-active="handler"` or a small adapter because the MCL event emits a string.
- Preserve content persistence with `v-show` where the old implementation relied on keeping child components mounted.

### 6. Migrate Complex Detail Pages

These pages mix routing, conditional visibility, extension tabs, errors, warnings, and plugin extension blocks.

Representative files:

- `src/module/sw-product/page/sw-product-detail/sw-product-detail.html.twig`
- `src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig`
- `src/module/sw-sales-channel/page/sw-sales-channel-detail/sw-sales-channel-detail.html.twig`
- `src/module/sw-customer/page/sw-customer-detail/sw-customer-detail.html.twig`
- `src/module/sw-promotion-v2/page/sw-promotion-v2-detail/sw-promotion-v2-detail.html.twig`
- `src/module/sw-settings-rule/page/sw-settings-rule-detail/sw-settings-rule-detail.html.twig`
- `src/module/sw-custom-entity/page/sw-generic-custom-entity-detail/sw-generic-custom-entity-detail.html.twig`

Implementation details:

- Build item arrays in computed properties.
- Filter hidden tabs out of the item array.
- Map error state to `hasError`.
- Map warning-only order document state to `badge: 'warning'`.
- Preserve `position-identifier` values for pages that are extension targets.
- Keep existing Twig blocks where possible, but remove blocks that only wrap removed legacy components if they cannot be meaningfully preserved.

### 7. Migrate Vertical Tabs and CMS Config Tabs

Vertical and CMS element config usages are usually local-state tabs.

Representative files:

- `src/module/sw-cms/page/sw-cms-list/sw-cms-list.html.twig`
- `src/module/sw-product/component/sw-product-variants/sw-product-modal-delivery/sw-product-modal-delivery.html.twig`
- `src/module/sw-product/component/sw-product-variants/sw-product-modal-variant-generation/sw-product-modal-variant-generation.html.twig`
- `src/module/sw-cms/elements/*/config/*.html.twig`

Implementation details:

- Replace `is-vertical` with `vertical`.
- Use local active state for content switching.
- Verify layout because MCL vertical tabs render different markup/classes than legacy `sw-tabs`.

### 8. Update Tests

Update tests in the same area as each migration.

Common changes:

- Replace `sw-tabs`, `sw-tabs-deprecated`, and `sw-tabs-item` stubs with `mt-tabs` stubs.
- Replace legacy selectors such as `.sw-tabs__content .sw-tabs-item` with MCL selectors or component assertions.
- Update event expectations because `new-item-active` now emits a tab name string, not a legacy item/component object.
- Add tests for item array creation where visibility, errors, warnings, or routing are non-trivial.

High-priority test files:

- `src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js`
- `src/app/component/form/sw-custom-field-set-renderer/sw-custom-field-set-renderer.spec.js`
- `src/app/component/meteor/sw-meteor-page/sw-meteor-page.spec.js`
- `src/app/component/meteor/sw-meteor-card/sw-meteor-card.spec.js`
- `src/module/sw-settings-search/page/sw-settings-search/sw-settings-search.spec.js`
- `src/module/sw-settings-country/page/sw-settings-country-detail/sw-settings-country-detail.spec.js`
- `src/module/sw-product/page/sw-product-detail/sw-product-detail.spec.js`
- `src/module/sw-product/view/sw-product-detail-variants/sw-product-detail-variants.spec.js`
- `src/module/sw-order/page/sw-order-detail/sw-order-detail.spec.js`

### 9. Add Lint Enforcement Against New Legacy Tab Usage

It makes sense to add lint enforcement, but it should be staged carefully because the migration intentionally keeps legacy `sw-tabs` branches while `V6_8_0_0` is inactive.

The Administration already uses deprecation linting in `eslint.config.mjs`:

- `sw-deprecation-rules/no-deprecated-components`
- `sw-deprecation-rules/no-deprecated-component-usage`

Preferred approach:

- Extend the existing deprecation-rule setup instead of adding a separate ad-hoc checker.
- Mark `sw-tabs`, `sw-tabs-item`, and direct `sw-tabs-deprecated` usage as deprecated for new code.
- Do not enable a strict global failure before compatibility branches are migrated, otherwise the current codebase and the required `V6_8_0_0` inactive branches will fail immediately.
- Allow legacy usage only in explicitly marked compatibility branches while the major feature flag exists.
- Make the rule strict after the major cleanup so no `sw-tabs`, `sw-tabs-item`, or `sw-tabs-deprecated` usage can be reintroduced.

Desired rule behavior during the feature-flag phase:

- Error on new `<sw-tabs>` or `<sw-tabs-item>` usage in normal templates.
- Allow old usage only in documented `V6_8_0_0` compatibility code paths, for example the `v-else` side of a `v-if="useMeteorTabs"` split.
- Report a clear message: `Use <mt-tabs :items="..."> instead. sw-tabs is only allowed in V6_8_0_0 compatibility branches.`
- Do not provide an autofix at first. Converting slot-based tab markup to `items` arrays requires component-specific route, active-state, visibility, and badge handling.

Final major-cleanup behavior:

- Remove the compatibility allowance.
- Treat any remaining `<sw-tabs>`, `<sw-tabs-item>`, and `<sw-tabs-deprecated>` usage as a hard lint error.
- Keep the lint rule/configuration active after migration to prevent regressions.

### 10. Remove Legacy Components in the Major Cleanup

After all usages and tests are migrated, remove the legacy components only in the `V6_8_0_0` major cleanup phase:

- Remove `Shopware.Component.register('sw-tabs', ...)`.
- Remove `Shopware.Component.register('sw-tabs-deprecated', ...)`.
- Remove `Shopware.Component.register('sw-tabs-item', ...)`.
- Delete legacy component implementations, templates, SCSS, and specs.
- Remove obsolete snippets under `global.sw-tabs-item` if no longer used.
- Remove documentation examples that still recommend `sw-tabs` or `sw-tabs-item`.
- Remove dual-path `v-else` legacy branches from migrated consumers.
- Remove compatibility adapters that only translate legacy `new-item-active` object payloads.

Expected files to delete include:

- `src/app/component/base/sw-tabs/`
- `src/app/component/base/sw-tabs-deprecated/`
- `src/app/component/base/sw-tabs-item/`

### 11. Final Search Cleanup

Run these searches and handle every remaining result:

```bash
rg 'sw-tabs|sw-tabs-item|sw-tabs-deprecated' src/Administration/Resources/app/administration
rg 'default-item|active-tab|is-vertical|align-right' src/Administration/Resources/app/administration/src --glob '*.html.twig'
rg '\.sw-tabs|\.sw-tabs-item' src/Administration/Resources/app/administration/src
```

Only intentional changelog, release note, or deprecation documentation references should remain.

## Verification Plan

Run targeted tests while migrating individual areas, then run the full Administration verification at the end.

Targeted examples:

```bash
cd src/Administration/Resources/app/administration
npx jest --collectCoverage=false src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js
npx jest --collectCoverage=false src/app/component/form/sw-custom-field-set-renderer/sw-custom-field-set-renderer.spec.js
npx jest --collectCoverage=false src/module/sw-product/page/sw-product-detail/sw-product-detail.spec.js
npx jest --collectCoverage=false src/module/sw-order/page/sw-order-detail/sw-order-detail.spec.js
```

Final required commands from repository guidelines:

```bash
composer eslint:admin:fix
composer format:admin:fix
composer stylelint:admin:fix
composer admin:unit
composer build:js:admin
```

Use `composer stylelint:admin:fix` only if SCSS changes are part of the migration.

## Main Risks

- Accidentally shipping the breaking removal in a minor or patch release without the major feature flag.
- Losing Admin Extension SDK `ui.tabs` integration if `positionIdentifier` behavior is removed from the Shopware `mt-tabs` wrapper.
- Breaking route-backed navigation by using unstable item names or forgetting `onClick` router pushes.
- Keeping conditionally hidden tabs selectable by leaving them in `items`.
- Breaking local content tabs by relying on removed `#content` slot active state.
- Regressing validation indicators if `hasError` and warning badges are not mapped explicitly.
- Breaking plugin extension points by removing Twig blocks or position identifiers too aggressively.
- Updating tests only as stubs without covering the new computed item arrays and active-state behavior.

## Recommended Rollout Order

1. Update and test the `mt-tabs` wrapper in a backward-compatible way.
2. Add flagged `mt-tabs` paths while keeping legacy `sw-tabs` paths for `V6_8_0_0` inactive state.
3. Migrate wrapper consumers behind the flag: `sw-meteor-page`, `sw-meteor-card`, `sw-extension-component-section`, `sw-custom-field-set-renderer`.
4. Migrate simple route-backed pages behind the flag.
5. Migrate local content tabs and modals behind the flag.
6. Migrate complex detail pages behind the flag.
7. Migrate vertical and CMS config tabs behind the flag.
8. Add staged lint enforcement that prevents new legacy tab usage while allowing documented compatibility branches.
9. In the major cleanup, remove legacy branches, components, snippets, docs, and tests.
10. Make the lint enforcement strict with no compatibility allowance.
11. Run final search cleanup and full verification.
