# MT Tabs Major Feature Flag Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate core Administration tab consumers to `mt-tabs :items` behind `V6_8_0_0` while preserving legacy `sw-tabs` behavior when the flag is inactive.

**Architecture:** Keep the legacy `sw-tabs` public contract available for inactive-flag compatibility. Add major-only template branches that use `mt-tabs` with explicit item arrays, and keep `position-identifier` extensions centralized in the `mt-tabs` wrapper. Add lint coverage that rejects new unguarded `sw-tabs` usage while allowing deliberate inactive-flag compatibility branches.

**Tech Stack:** Shopware Administration, Vue 3 compat, TwigJS templates, Meteor Component Library `mt-tabs`, Shopware feature flags, ESLint custom rules, Jest.

---

## File Map

- Modify `src/Administration/Resources/app/administration/eslint-rules/deprecation-rules/no-deprecated-components.js`: detect `sw-tabs`/`sw-tabs-item` usage and allow it only inside an inactive `V6_8_0_0` compatibility branch.
- Modify `src/Administration/Resources/app/administration/eslint-rules/deprecation-rules/no-deprecated-components.spec.js`: add valid and invalid lint rule coverage for `sw-tabs`, `sw-tabs-item`, and guarded compatibility branches.
- Modify `src/Administration/Resources/app/administration/src/app/component/meteor-wrapper/mt-tabs/index.ts`: keep extension item merging and event compatibility for migrated consumers.
- Modify `src/Administration/Resources/app/administration/src/app/component/meteor-wrapper/mt-tabs/mt-tabs.html.twig`: pass through attrs, refs, items, and active-item events to the Meteor component.
- Modify `src/Administration/Resources/app/administration/src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js`: cover item merging, route clicks, empty state, and active item event pass-through.
- Modify `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs/index.ts`: add major-deprecation metadata/comments without removing legacy behavior.
- Modify `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs/sw-tabs.html.twig`: keep inactive-flag legacy behavior and major behavior compatible.
- Modify `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs/sw-tabs.spec.js`: verify inactive and active flag behavior.
- Modify `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs-deprecated/index.js`: add major-deprecation metadata/comments only.
- Modify `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs-item/index.js`: add major-deprecation metadata/comments only.
- Modify each consumer listed in Task 4: add a `V6_8_0_0` major branch with `mt-tabs :items` and keep the existing legacy branch intact.
- Modify adjacent `.spec.js` files for migrated consumers when present: test inactive legacy behavior and active major behavior for representative consumers.

## Task 1: Add Lint Rule Coverage For Deprecated Tabs

**Files:**
- Modify: `src/Administration/Resources/app/administration/eslint-rules/deprecation-rules/no-deprecated-components.js`
- Modify: `src/Administration/Resources/app/administration/eslint-rules/deprecation-rules/no-deprecated-components.spec.js`

- [ ] **Step 1: Add failing lint tests for guarded and unguarded usage**

Add these cases to the existing invalid and valid sections in `no-deprecated-components.spec.js` near the existing `sw-tabs` tests:

```js
{
    name: '"sw-tabs" usage is allowed in inactive major compatibility branch',
    filename: 'test.html.twig',
    code: `
<template>
    <template v-if="Shopware.Feature.isActive('V6_8_0_0')">
        <mt-tabs :items="items" />
    </template>

    <template v-else>
        <sw-tabs>
            <sw-tabs-item name="general">
                General
            </sw-tabs-item>
        </sw-tabs>
    </template>
</template>`,
},
{
    name: '"sw-tabs" usage is not allowed outside inactive major compatibility branch',
    filename: 'test.html.twig',
    options: [{ fix: false }],
    code: `
<template>
    <sw-tabs>
        <sw-tabs-item name="general">
            General
        </sw-tabs-item>
    </sw-tabs>
</template>`,
    errors: [
        { message: '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.' },
        { message: '"sw-tabs-item" is deprecated. Please use "mt-tabs" with the "items" property instead.' },
    ],
}
```

- [ ] **Step 2: Run the lint rule test and verify it fails**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false eslint-rules/deprecation-rules/no-deprecated-components.spec.js
```

Expected: FAIL because `sw-tabs-item` is not rejected and guarded `sw-tabs` handling is not implemented.

- [ ] **Step 3: Implement guarded legacy detection**

In `no-deprecated-components.js`, add a helper above `module.exports`:

```js
function getDirectiveValue(node, directiveName) {
    const attribute = node.startTag?.attributes?.find((candidate) => {
        return candidate.key?.name?.name === directiveName;
    });

    return attribute?.value?.expression?.source ?? attribute?.value?.value ?? '';
}

function isInactiveMajorCompatibilityBranch(node) {
    let currentNode = node.parent;

    while (currentNode) {
        const elseIfValue = getDirectiveValue(currentNode, 'else-if');

        if (elseIfValue.includes('V6_8_0_0') && elseIfValue.includes('!')) {
            return true;
        }

        const hasElse = currentNode.startTag?.attributes?.some((attribute) => {
            return attribute.key?.name === 'else';
        });

        if (hasElse) {
            const siblings = currentNode.parent?.children ?? [];
            const currentIndex = siblings.indexOf(currentNode);
            const previousElement = siblings.slice(0, currentIndex).reverse().find((sibling) => {
                return sibling.type === 'VElement';
            });

            const previousIfValue = getDirectiveValue(previousElement, 'if');

            if (previousIfValue.includes('V6_8_0_0')) {
                return true;
            }
        }

        currentNode = currentNode.parent;
    }

    return false;
}
```

Then, before the generic deprecated component conversion, add a tabs-specific report:

```js
const deprecatedTabComponents = ['sw-tabs', 'sw-tabs-item'];

if (deprecatedTabComponents.includes(node.name)) {
    if (isInactiveMajorCompatibilityBranch(node)) {
        return;
    }

    context.report({
        loc: node.loc,
        message: `"${node.name}" is deprecated. Please use "mt-tabs" with the "items" property instead.`,
    });

    return;
}
```

Remove duplicate `sw-tabs` entries from the generic deprecated component arrays so this specific rule owns tab reporting.

- [ ] **Step 4: Run the lint rule test and verify it passes**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false eslint-rules/deprecation-rules/no-deprecated-components.spec.js
```

Expected: PASS.

## Task 2: Harden The `mt-tabs` Wrapper For Major Consumers

**Files:**
- Modify: `src/Administration/Resources/app/administration/src/app/component/meteor-wrapper/mt-tabs/index.ts`
- Modify: `src/Administration/Resources/app/administration/src/app/component/meteor-wrapper/mt-tabs/mt-tabs.html.twig`
- Modify: `src/Administration/Resources/app/administration/src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js`

- [ ] **Step 1: Add failing wrapper tests**

Add tests in `mt-tabs.spec.js` that mount `mt-tabs` with `positionIdentifier="example-tabs"` and `items={[{ label: 'General', name: 'general' }]}`. Test these behaviors:

```js
expect(wrapper.props('items')).toEqual([{ label: 'General', name: 'general' }]);
expect(wrapper.findComponent({ name: 'mt-tabs-original' }).props('items')).toEqual([
    { label: 'General', name: 'general' },
]);
```

Also seed `Shopware.Store.get('tabs').tabItems['example-tabs']` with one extension entry and assert the rendered `mt-tabs-original` receives the core item followed by the extension item.

- [ ] **Step 2: Run the wrapper test and verify current gaps**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js
```

Expected: FAIL if active event pass-through, extension item shape, or empty state is missing.

- [ ] **Step 3: Implement minimal wrapper behavior**

Keep `positionIdentifier` optional only if existing usage requires it; otherwise keep it required. Ensure `mergedItems` returns `this.items` plus tab extension entries and that extension route clicks push the extension path. In `mt-tabs.html.twig`, keep this shape:

```twig
{% block mt_tabs %}
<mt-tabs-original
    v-bind="$attrs"
    ref="mtTabsOriginal"
    :items="mergedItems"
    @new-item-active="$emit('new-item-active', $event)"
/>
{% endblock %}
```

- [ ] **Step 4: Run the wrapper test and verify it passes**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js
```

Expected: PASS.

## Task 3: Preserve And Deprecate Legacy Components

**Files:**
- Modify: `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs/index.ts`
- Modify: `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs/sw-tabs.html.twig`
- Modify: `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs/sw-tabs.spec.js`
- Modify: `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs-deprecated/index.js`
- Modify: `src/Administration/Resources/app/administration/src/app/component/base/sw-tabs-item/index.js`

- [ ] **Step 1: Add failing compatibility tests for `sw-tabs`**

In `sw-tabs.spec.js`, cover both feature flag states:

```js
it('renders sw-tabs-deprecated when V6_8_0_0 is inactive', async () => {
    Shopware.Feature.getAll().V6_8_0_0 = false;

    const wrapper = await createWrapper();

    expect(wrapper.findComponent({ name: 'sw-tabs-deprecated' }).exists()).toBe(true);
});

it('renders mt-tabs when V6_8_0_0 is active', async () => {
    Shopware.Feature.getAll().V6_8_0_0 = true;

    const wrapper = await createWrapper({
        props: {
            items: [{ label: 'General', name: 'general' }],
        },
    });

    expect(wrapper.find('mt-tabs-stub').exists()).toBe(true);
});
```

- [ ] **Step 2: Run the legacy component tests**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false src/app/component/base/sw-tabs/sw-tabs.spec.js
```

Expected: PASS before deprecation-only changes; any failure indicates a BC regression to fix before continuing.

- [ ] **Step 3: Add major-deprecation annotations only**

Add `@major-deprecated` comments to `sw-tabs`, `sw-tabs-deprecated`, and `sw-tabs-item` component metadata. Do not remove props, refs, slots, methods, or registrations.

Use this comment shape near component metadata:

```js
/**
 * @major-deprecated tag:v6.8.0 - Use mt-tabs with the items property instead.
 */
```

- [ ] **Step 4: Re-run the legacy component tests**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false src/app/component/base/sw-tabs/sw-tabs.spec.js
```

Expected: PASS.

## Task 4: Migrate Core Consumers Behind `V6_8_0_0`

**Files:**
- Modify each template in this inventory and its adjacent `index.js`, `index.ts`, or spec file when tab items or tests are needed:

```text
src/Administration/Resources/app/administration/src/module/sw-extension/page/sw-extension-my-extensions-index/sw-extension-my-extensions-index.html.twig
src/Administration/Resources/app/administration/src/module/sw-category/component/sw-landing-page-view/sw-landing-page-view.html.twig
src/Administration/Resources/app/administration/src/module/sw-category/component/sw-category-view/sw-category-view.html.twig
src/Administration/Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-index/sw-mail-template-index.html.twig
src/Administration/Resources/app/administration/src/module/sw-promotion-v2/page/sw-promotion-v2-detail/sw-promotion-v2-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-import-export/component/sw-import-export-edit-profile-modal/sw-import-export-edit-profile-modal.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-search/component/sw-settings-search-searchable-content/sw-settings-search-searchable-content.html.twig
src/Administration/Resources/app/administration/src/module/sw-users-permissions/page/sw-users-permissions-role-detail/sw-users-permissions-role-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-profile/page/sw-profile-index/sw-profile-index.html.twig
src/Administration/Resources/app/administration/src/module/sw-import-export/page/sw-import-export/sw-import-export.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-search/page/sw-settings-search/sw-settings-search.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-logging/component/sw-settings-logging-entry-info/sw-settings-logging-entry-info.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-logging/component/sw-settings-logging-mail-sent-info/sw-settings-logging-mail-sent-info.html.twig
src/Administration/Resources/app/administration/src/module/sw-media/component/sw-media-modal-v2/sw-media-modal-v2.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/page/sw-cms-list/sw-cms-list.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/image-gallery/config/sw-cms-el-config-image-gallery.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-country/page/sw-settings-country-detail/sw-settings-country-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-slider/config/sw-cms-el-config-product-slider.html.twig
src/Administration/Resources/app/administration/src/module/sw-flow/component/modals/sw-flow-rule-modal/sw-flow-rule-modal.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/cross-selling/config/sw-cms-el-config-cross-selling.html.twig
src/Administration/Resources/app/administration/src/app/component/media/sw-media-modal-folder-settings/sw-media-modal-folder-settings.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/text/config/sw-cms-el-config-text.html.twig
src/Administration/Resources/app/administration/src/module/sw-product/view/sw-product-detail-variants/sw-product-detail-variants.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/buy-box/config/sw-cms-el-config-buy-box.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/image-slider/config/sw-cms-el-config-image-slider.html.twig
src/Administration/Resources/app/administration/src/module/sw-flow/page/sw-flow-index/sw-flow-index.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-description-reviews/config/sw-cms-el-config-product-description-reviews.html.twig
src/Administration/Resources/app/administration/src/module/sw-flow/page/sw-flow-detail/sw-flow-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variants/sw-product-modal-delivery/sw-product-modal-delivery.html.twig
src/Administration/Resources/app/administration/src/module/sw-sales-channel/page/sw-sales-channel-detail/sw-sales-channel-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/form/config/sw-cms-el-config-form.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-listing/config/sw-cms-el-config-product-listing.html.twig
src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variants/sw-product-modal-variant-generation/sw-product-modal-variant-generation.html.twig
src/Administration/Resources/app/administration/src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-modal/sw-sales-channel-products-assignment-modal.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-tag/component/sw-settings-tag-detail-modal/sw-settings-tag-detail-modal.html.twig
src/Administration/Resources/app/administration/src/module/sw-custom-entity/page/sw-generic-custom-entity-detail/sw-generic-custom-entity-detail.html.twig
src/Administration/Resources/app/administration/src/app/component/extension-api/sw-extension-component-section/sw-extension-component-section.html.twig
src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-detail/sw-product-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-custom-field/component/sw-custom-field-translated-labels/sw-custom-field-translated-labels.html.twig
src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-layout-assignment-modal/sw-cms-layout-assignment-modal.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-usage-data/page/sw-settings-usage-data/sw-settings-usage-data.html.twig
src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-detail/sw-customer-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-settings-rule/page/sw-settings-rule-detail/sw-settings-rule-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig
src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-create-initial-modal/sw-order-create-initial-modal.html.twig
src/Administration/Resources/app/administration/src/module/sw-order/page/sw-order-create/sw-order-create.html.twig
src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-address-modal/sw-order-address-modal.html.twig
src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-new-customer-modal/sw-order-new-customer-modal.html.twig
src/Administration/Resources/app/administration/src/app/component/form/sw-custom-field-set-renderer/sw-custom-field-set-renderer.html.twig
src/Administration/Resources/app/administration/src/app/component/meteor/sw-meteor-page/sw-meteor-page.html.twig
src/Administration/Resources/app/administration/src/app/component/meteor/sw-meteor-card/sw-meteor-card.html.twig
```

- [ ] **Step 1: Pick one consumer and write flag-state tests first**

Start with `src/Administration/Resources/app/administration/src/module/sw-users-permissions/page/sw-users-permissions-role-detail/sw-users-permissions-role-detail.spec.js` because it has a compact tab set. Add one test with `V6_8_0_0` inactive that asserts `sw-tabs` is still rendered, and one test with `V6_8_0_0` active that asserts `mt-tabs` receives the expected `items`.

- [ ] **Step 2: Run the selected consumer test and verify it fails for the active major path**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false src/module/sw-users-permissions/page/sw-users-permissions-role-detail/sw-users-permissions-role-detail.spec.js
```

Expected: FAIL for the active major test because the template still renders the legacy branch only.

- [ ] **Step 3: Add the consumer migration pattern**

For each migrated component, add these component members when the component does not already expose equivalents:

```js
computed: {
    isMajorTabsMigrationActive() {
        return Shopware.Feature.isActive('V6_8_0_0');
    },

    tabItems() {
        return [
            {
                label: this.$tc('sw-users-permissions.roles.tabs.general'),
                name: 'general',
            },
            {
                label: this.$tc('sw-users-permissions.roles.tabs.permissions'),
                name: 'permissions',
            },
        ];
    },
},

data() {
    return {
        activeTab: 'general',
    };
},

methods: {
    onNewItemActive(activeItem) {
        this.activeTab = activeItem;
    },
},
```

Use the component's real snippet keys and tab names from the existing `sw-tabs-item` markup. Keep existing methods if they already handle active tab changes.

- [ ] **Step 4: Add the major template branch**

Use this exact structure, adapted to each component's existing blocks and tab names:

```twig
<template v-if="isMajorTabsMigrationActive">
    <mt-tabs
        position-identifier="sw-users-permissions-role-detail"
        :items="tabItems"
        @new-item-active="onNewItemActive"
    />

    <template v-if="activeTab === 'general'">
        <!-- existing general tab content moves here -->
    </template>

    <template v-if="activeTab === 'permissions'">
        <!-- existing permissions tab content moves here -->
    </template>
</template>

<template v-else>
    <!-- existing sw-tabs markup remains unchanged here -->
</template>
```

When moving content, preserve existing Twig block names inside their original branch when possible. If a block must move for the major branch, add a new major-only block and keep the old block in the legacy branch.

- [ ] **Step 5: Run the selected consumer test and verify it passes**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false src/module/sw-users-permissions/page/sw-users-permissions-role-detail/sw-users-permissions-role-detail.spec.js
```

Expected: PASS.

- [ ] **Step 6: Repeat the same TDD cycle for each inventory file**

For every file in the Task 4 inventory, follow Steps 1 through 5. Use the nearest adjacent `.spec.js` file when one exists. If no adjacent spec exists, add coverage to the closest existing component spec for the same component directory.

Expected after each migrated consumer: inactive flag tests keep passing and active flag tests prove `mt-tabs` receives the expected `items`.

## Task 5: Remove Unguarded Core Legacy Usage

**Files:**
- Modify: every file still found by the verification commands below.

- [ ] **Step 1: Search for remaining legacy templates**

Run from the repository root:

```bash
rg '<sw-tabs|<sw-tabs-item' src/Administration/Resources/app/administration/src
```

Expected: Matches only in legacy inactive feature-flag branches and legacy component implementation files.

- [ ] **Step 2: Search for unguarded legacy usage**

Run from the repository root:

```bash
composer eslint:admin -- --rule 'sw-deprecation-rules/no-deprecated-components: error'
```

Expected: PASS. Any failure must be fixed by migrating the usage or moving it into an explicit inactive `V6_8_0_0` compatibility branch.

## Task 6: Final Verification

**Files:**
- Verify all modified Administration files.

- [ ] **Step 1: Run Administration autofixers**

Run from the repository root:

```bash
composer eslint:admin:fix
composer format:admin:fix
```

Expected: both commands complete successfully. Only keep autofixes related to files touched by this migration.

- [ ] **Step 2: Run targeted Jest tests**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false eslint-rules/deprecation-rules/no-deprecated-components.spec.js src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js src/app/component/base/sw-tabs/sw-tabs.spec.js
```

Expected: PASS.

- [ ] **Step 3: Run all Administration Jest tests if feasible**

Run from `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false
```

Expected: PASS. If the full suite is not feasible locally, record the reason and include all targeted test results.

- [ ] **Step 4: Review git diff for BC hazards**

Run from the repository root:

```bash
git diff -- src/Administration/Resources/app/administration docs/superpowers
```

Expected: no unflagged removal of `sw-tabs`, `sw-tabs-deprecated`, `sw-tabs-item`, legacy slots, old props, refs, Twig blocks, or CSS hooks.

## Self-Review

- Spec coverage: The plan covers the feature-flagged full migration, the inactive legacy contract, `position-identifier` extension behavior, the lint rule, tests for both flag states, and final verification.
- Placeholder scan: The plan has no `TBD` or open-ended implementation steps. The only HTML comments in code examples identify where existing tab content is moved during the mechanical migration.
- Type consistency: The plan consistently uses `V6_8_0_0`, `mt-tabs`, `items`, `positionIdentifier`, `position-identifier`, `new-item-active`, `tabItems`, `activeTab`, and `isMajorTabsMigrationActive`.

## Commit Policy

Do not commit during execution unless the user explicitly asks for commits. Use `git status --short` and `git diff` after each task to keep changes reviewable.
