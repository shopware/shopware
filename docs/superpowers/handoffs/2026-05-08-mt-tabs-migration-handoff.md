# MT Tabs Migration Handoff Prompt

Use this prompt in a fresh session:

```text
Continue the Shopware Administration `sw-tabs` / `sw-tabs-item` to `mt-tabs` migration in `/Users/jannisleifeld/Sites/shopware-localhost` on branch `sw-tabs-migration-to-mt-tabs`.

First steps:
- Read `AGENTS.md` and nearest relevant `AGENTS.md` files for touched paths.
- Read `docs/superpowers/specs/2026-05-07-mt-tabs-major-feature-flag-migration-design.md`.
- Read `docs/superpowers/plans/2026-05-07-mt-tabs-major-feature-flag-migration.md`.
- Check `git status --short` before editing.
- Use `subagent-driven-development` for implementation batches.
- Use `shopware-backward-compatibility` for Administration BC review.
- Use `receiving-code-review` before applying reviewer/subagent feedback.
- Use `verification-before-completion` before claiming completion.

PR context:
- `shopware/shopware#14822`
- Branch: `sw-tabs-migration-to-mt-tabs`
- Branch is currently ahead of `origin/sw-tabs-migration-to-mt-tabs` by 3 commits:
  - `a70bf54e08a feat: migrate admin tabs behind major flag`
  - `91af08289b9 feat: migrate flow and media tabs behind major flag`
  - `d26fbfacf23 feat: migrate order modal tabs behind major flag`
- Current session has a large uncommitted working tree containing Batches 0-7 after `d26fbfacf23`.
- This handoff file itself is untracked/modified and should be included or ignored intentionally later.
- Do not commit unless explicitly asked.

Migration rules:
- Active `V6_8_0_0` branches must use real `<mt-tabs :items="...">`.
- Inactive branches must preserve existing legacy `sw-tabs`, `sw-tabs-deprecated`, and `sw-tabs-item` markup and behavior for BC.
- Preserve legacy Twig blocks, slots, refs, classes, routes, props, events, `position-identifier` values, and extension points.
- For Twig `Shopware.Feature.isActive('V6_8_0_0')` checks, expose computed `Shopware() { return Shopware; }`.
- Use supported `mt-tabs` props/item fields only: `items`, `defaultItem` / `default-item`, `small`, `vertical`, `positionIdentifier` / `position-identifier`, `routeExtensionTabs` / `route-extension-tabs`, and item fields such as `label`, `name`, `hasError`, `disabled`, `badge`, `onClick`.
- Do not assert unsupported item metadata such as `class` or `route` as UI behavior.
- For route tabs, keep route-backed `onClick` and route-derived `defaultItem`.
- For local content tabs, keep local `activeTab`, normalize `new-item-active` string/object payloads, use `:route-extension-tabs="false"`, and render `sw-extension-component-section` for registered extension tab IDs when the legacy position supported local extension tabs.
- Unknown/stale tab IDs must not blank active panes; accept only known core tab names or registered extension `componentSectionId`s.
- Avoid duplicate activation: Meteor `MtTabs` emits `new-item-active` before invoking item `onClick`.
- Do not revert or commit unrelated changes.

Completed before this handoff:
- Batch 0: compact batch from current session
  - `sw-media-modal-v2`
  - `sw-mail-template-index`
  - `sw-order-create-initial-modal`
  - `sw-category-view`
- Batch 1:
  - `sw-landing-page-view`
  - `sw-extension-my-extensions-index`
  - additive `sw-meteor-page` support for `pageTabs`, `pageTabsDefaultItem`, `pageTabsPositionIdentifier`, legacy slot fallback, and event forwarding
- Batch 2:
  - `sw-order-create`
  - `sw-order-detail`
- Batch 3:
  - `sw-customer-detail`
  - `sw-promotion-v2-detail`
- Batch 4:
  - `sw-sales-channel-detail`
  - `sw-sales-channel-products-assignment-modal`
- Batch 5:
  - `sw-product-detail`
  - `sw-product-detail-variants`
  - `sw-product-modal-delivery`
  - `sw-product-modal-variant-generation`
- Batch 6:
  - `mt-tabs` wrapper
  - `sw-meteor-card`
  - `sw-extension-component-section`
  - `sw-custom-field-set-renderer`
- Batch 7:
  - `sw-cms-list`
  - `sw-cms-layout-assignment-modal`
  - CMS element configs: `text`, `form`, `buy-box`, `cross-selling`, `product-description-reviews`, `image-gallery`, `product-slider`, `image-slider`, `product-listing`

Review status:
- Batches 1-7 went through implementer subagent plus spec and quality review subagents.
- All final reviews for Batches 1-7 ended as `SPEC COMPLIANT` and `APPROVED` after fixes.
- Multiple important review fixes were applied, including:
  - preserving `sw-meteor-page` and `sw-meteor-card` legacy slot fallback behavior
  - preventing duplicate `new-item-active` emissions in local extension tab mode
  - preserving local extension tab content via `sw-extension-component-section`
  - guarding unknown/stale tab IDs
  - preserving vertical tab layout in product variant modals
  - preserving disabled tab behavior and avoiding event bypasses
  - preserving CMS product-detail assignment reachability when extension tabs exist

Verification already run in the current session:
- Batch 5 final targeted Jest: 4 suites passed, 80 tests passed.
- Batch 6 final targeted Jest: 4 suites passed, 152 tests passed.
- Batch 7 final targeted Jest:
  `npx jest --collectCoverage=false src/module/sw-cms/page/sw-cms-list/sw-cms-list.spec.js src/module/sw-cms/component/sw-cms-layout-assignment-modal/sw-cms-layout-assignment-modal.spec.js src/module/sw-cms/elements/text/config/config.spec.js src/module/sw-cms/elements/form/config/config.spec.js src/module/sw-cms/elements/buy-box/config/config.spec.js src/module/sw-cms/elements/cross-selling/config/config.spec.js src/module/sw-cms/elements/product-description-reviews/config/config.spec.js src/module/sw-cms/elements/image-gallery/config/config.spec.js src/module/sw-cms/elements/product-slider/config/config.spec.js src/module/sw-cms/elements/image-slider/config/config.spec.js src/module/sw-cms/elements/product-listing/config/config.spec.js`
  Result: 11 suites passed, 176 tests passed, 3 skipped.
- Batch 7 scoped ESLint passed.
- Batch 7 deprecated-tabs check passed for scoped Twig files.
- `git diff --check` passed after Batch 7.

Current deprecated-tabs scan result:
Run from `src/Administration/Resources/app/administration`:
`npx eslint --rule 'sw-deprecation-rules/no-deprecated-components: error' $(rg -l --glob '*.html.twig' '<sw-tabs|<sw-tabs-item' 'src')`

Current remaining output:
- `src/app/component/base/sw-tabs-deprecated/sw-tabs-deprecated.html.twig:17:9`
  - error: `sw-tabs-item` is deprecated.
- `src/module/sw-custom-entity/page/sw-generic-custom-entity-detail/sw-generic-custom-entity-detail.html.twig`
  - line 31: `sw-tabs` error
  - lines 39, 49, 58: `sw-tabs-item` errors
- Known warnings still present:
  - `src/app/component/media/sw-media-modal-folder-settings/sw-media-modal-folder-settings.html.twig:335:29` label-has-for warning in preserved legacy markup
  - `src/module/sw-settings-search/component/sw-settings-search-searchable-content/sw-settings-search-searchable-content.html.twig:54:13` click-events-have-key-events warning in preserved legacy markup

Recommended next batch:
- Batch 8: handle remaining deprecated-tabs scan exceptions.
- Start with `sw-generic-custom-entity-detail`:
  - migrate active `V6_8_0_0` branch to `mt-tabs :items`
  - preserve inactive legacy `sw-tabs` branch and old Twig blocks
  - add/adjust adjacent Jest coverage for inactive legacy, active major, default item, and route click behavior
  - verify extension/local tab semantics if its tabs are not purely route-backed
- Then handle `sw-tabs-deprecated.html.twig`:
  - this is legacy implementation code, not a normal consumer
  - decide whether the lint rule should explicitly exempt the legacy implementation file or whether the template needs an inactive-feature branch marker accepted by the rule
  - preserve legacy behavior; do not remove `sw-tabs-item` from the deprecated component without careful BC review

After Batch 8:
- Run deprecated-tabs scan again. Expected: only accepted legacy implementation exceptions and known warnings, or clean if the rule exempts legacy implementation.
- Run broad targeted Jest for all changed specs from Batches 0-8 if feasible.
- Run `composer eslint:admin:fix` and `composer format:admin:fix` from repo root.
- Run `composer eslint:admin` or at least targeted admin ESLint.
- Run all relevant targeted Jest again; full `npx jest --collectCoverage=false` if feasible.
- Run `git diff --check`.
- Review `git diff -- src/Administration/Resources/app/administration docs/superpowers` for BC hazards:
  - no unflagged removal of `sw-tabs`, `sw-tabs-deprecated`, `sw-tabs-item`
  - no removed legacy slots, old props, refs, Twig blocks, classes, routes, or extension positions
  - active major paths use supported `mt-tabs` props/fields only
  - local extension tabs render content and do not route away unless route-backed behavior is intentional

Useful commands:
- `git status --short`
- `git diff --stat`
- From repo root: `git diff --check`
- From `src/Administration/Resources/app/administration`: targeted `npx jest --collectCoverage=false ...`
- From `src/Administration/Resources/app/administration`: targeted `npx eslint --fix ...` and `npx eslint ...`
- From `src/Administration/Resources/app/administration`: deprecated-tabs scan command above

Important: The user requested a handoff file, not a commit. Do not commit unless explicitly asked.
```
