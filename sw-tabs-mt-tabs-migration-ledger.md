# sw-tabs to mt-tabs Migration Ledger

Source plan: `sw-tabs-mt-tabs-migration-plan.md`

## Global Safety Rules

- Keep `sw-tabs`, `sw-tabs-deprecated`, and `sw-tabs-item` available while `V6_8_0_0` is inactive.
- Add new `mt-tabs` paths behind `V6_8_0_0`; do not remove legacy paths in this phase.
- Preserve `position-identifier` values, Twig blocks, refs, and extension-facing slots unless explicitly noted.
- Use route names as route-backed tab item names.
- Filter conditional tabs out of `items` instead of rendering hidden selectable items.
- Use local active state for content-slot migrations.
- Map validation state to `hasError` and warning-only indicators to `badge: 'warning'`.

## Batch Status

| Batch | Scope | Status | Verification | Notes |
| --- | --- | --- | --- | --- |
| 1 | `mt-tabs` wrapper stabilization | completed | `mt-tabs.spec.js`, lint/format | SDK `ui.tabs` bridge kept; `positionIdentifier` optional. |
| 2 | Shared wrapper consumers | completed | 5 targeted specs (160 tests), lint/format | Dual paths added for `sw-meteor-page`, `sw-meteor-card`, extension/custom-field wrappers. |
| 3 | Simple route-backed pages | completed | 8 targeted specs (64 tests), lint/format | Route item arrays added for import/export, usage data, profile, mail templates, settings search, role detail, country detail; SDK route-tab active state handled in `mt-tabs`. |
| 4 | State-backed content tabs | completed | 15 targeted specs (211 tests), lint/format | Migrated tag detail modal, order address modal, custom-field translated labels, CMS text config, product delivery modal, new customer modal, order initial modal, sales-channel assignment modal, flow rule modal, settings searchable content, media folder settings, media modal v2; SDK extension tab activation now hides core panes and renders card extension content in the card body. |
| 5 | Complex detail pages | completed | 7 targeted specs (156 tests), lint/format | Migrated promotion, customer, rule, order, sales channel, product, and generic custom entity detail pages. |
| 6 | Vertical/CMS config tabs | completed | 12 targeted specs (160 tests), lint/format | Migrated CMS configs for buy-box, product-description-reviews, cross-selling, form, product-slider, product-listing, image-slider, image-gallery; migrated product variant generation, product detail variants, CMS list vertical tabs, and layout assignment modal. |
| 7 | Search-gate cleanup and staged lint enforcement | completed | 51 changed specs (664 passed, 3 skipped), targeted lint/format/search gate | Migrated remaining non-compatibility usages in flow index/detail, order create, category/landing-page views, extension my-extensions tabs, import-export edit profile modal, and logging entry/mail-sent info. Remaining legacy tags are intentional compatibility/base-component branches. |
| 8 | Major cleanup | deferred | Full admin unit/build/final searches | Only when major flag cleanup is intended. |

## Per-Batch Exit Criteria

- Changed files are limited to the declared batch scope.
- Legacy branches remain for inactive `V6_8_0_0` where BC requires them.
- Targeted Jest tests pass or failures are documented with root cause.
- `composer eslint:admin:fix` and `composer format:admin:fix` are run after code edits.
- Search gates show only intentional legacy compatibility references in changed areas.
- A BC/code-review pass has no unresolved blocker/high findings.
