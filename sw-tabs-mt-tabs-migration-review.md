# sw-tabs to mt-tabs Migration Review

## Findings

- [Medium] `sw-tabs-mt-tabs-migration-ledger.md:25` - Verification evidence is incomplete
  The ledger marks the migration complete with "51 changed specs (664 passed, 3 skipped), targeted lint/format/search gate", but the full command results were not verifiable from repo artifacts during this review. Two new spec files are currently untracked, so they are not part of the tracked diff evidence:
  `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-landing-page-view/sw-landing-page-view.spec.js`
  `src/Administration/Resources/app/administration/src/module/sw-import-export/page/sw-import-export/sw-import-export.spec.js`
  If those specs are required for the migration evidence, they need to be included. The untracked `vendor-bin/cs-fixer/` directory also looks unrelated/noisy and should not be included accidentally.

## Verdict

The migration plan appears to have been implemented correctly from the sampled and delegated review passes. I did not find a confirmed runtime, route-tab, SDK tab bridge, active-state, or backward-compatibility regression.

The main issue is evidence hygiene: the ledger overstates verification completeness unless the missing command logs/artifacts exist elsewhere and the untracked specs are intentionally excluded or added.

## Testing

- Ran targeted Jest verification:
  `npx jest --collectCoverage=false src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js src/app/component/extension-api/sw-extension-component-section/sw-extension-component-section.spec.js src/module/sw-settings-search/component/sw-settings-search-searchable-content/sw-settings-search-searchable-content.spec.js src/module/sw-custom-entity/page/sw-generic-custom-entity-detail/sw-generic-custom-entity-detail.spec.js`
- Result: 4 suites passed, 30 tests passed.
- Full `composer admin:unit`, `composer eslint:admin`, `composer format:admin`, and `composer build:js:admin` were not run as part of this review.

## Review Notes

- Subagents were used for focused slices across core wrappers/extensibility, route/detail tabs, local content/CMS/modal tabs, backward compatibility, and tests/search gates.
- The suspected issues around `sw-extension-component-section`, `sw-generic-custom-entity-detail`, and `sw-settings-search-searchable-content` were manually re-checked and did not hold up under direct inspection and targeted tests.
- Remaining `sw-tabs`, `sw-tabs-deprecated`, and `sw-tabs-item` references are expected while the `V6_8_0_0` compatibility branches remain.
