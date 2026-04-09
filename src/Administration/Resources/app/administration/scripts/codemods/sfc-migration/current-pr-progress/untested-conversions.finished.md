# Resolved: All Conversion Paths Now Have Test Fixtures

**Status:** ✅ Done — all conversion paths listed below now have dedicated fixtures and test cases. A bug in `findOptionsObject` was also fixed.

---

## What was done

New fixture files created in `__fixtures__/`:

| Fixture | Covers |
|---------|--------|
| `composables-component.index.js` + `.html.twig` | `$router` → `useRouter()`, `$route` → `useRoute()`, `$slots` → `useSlots()`, `$nextTick` → `nextTick()`, `$t`/`$tc` via `useI18n()`, `$el` → `/* TODO: $el */ getCurrentInstance()?.proxy?.$el` |
| `inherit-attrs-component.index.js` + `.html.twig` | `inheritAttrs: false` → `defineOptions({ inheritAttrs: false })` |
| `extend-component.index.js` | `Shopware.Component.extend()` soft blocker → `partially-migratable`, blockers `['extends']`, Options API backoff |
| `extends-template.html.twig` | `{% extends '...' %}` line stripped, adjacent eslint-disable comment stripped, block syntax still converted |

New test coverage added:

- `transform-script.spec.ts`: 3 new `describe` blocks (composables, inherit-attrs, extend) — 15 new tests
- `transform-template.spec.ts`: 1 new `describe` block (extends-template) — 6 new tests

### Bug fixed in `transform-script.ts`

`findOptionsObject` was reading `getArguments()[1]` for all call types. For `Shopware.Component.extend('name', 'parent', {...})`, the options object is at index 2 (the third argument), not index 1. This caused `.extend()` components to be incorrectly classified as `not-migratable` with blocker `'no options object found'` instead of `partially-migratable` with blocker `'extends'`. Fixed by detecting the extend call and reading from the correct argument index.

---

## Coverage summary

| Conversion path | Status |
|----------------|--------|
| `this.$router` → `useRouter()` | ✅ Tested |
| `this.$route` → `useRoute()` | ✅ Tested |
| `this.$slots` → `useSlots()` | ✅ Tested |
| `this.$nextTick` → `nextTick()` | ✅ Tested |
| `this.$t` → `useI18n().t` | ✅ Tested |
| `this.$tc` → `useI18n().tc` | ✅ Tested |
| `this.$el` → `getCurrentInstance()?.proxy?.$el` with TODO | ✅ Tested |
| `inheritAttrs: false` → `defineOptions(...)` | ✅ Tested |
| `Shopware.Component.extend()` soft blocker | ✅ Tested (+ bug fixed) |
| `{% extends '...' %}` stripping in Twig | ✅ Tested |
| `export default {}` normalization | ✅ Already tested in `run-sfc-migration.spec.ts` |
| File-system behaviors of runner | ✅ Already tested in `run-sfc-migration.spec.ts` |

All 159 tests in the SFC migration suite pass.

---

## Acceptance check

- [x] Each conversion path listed above has a dedicated test case (fixture + assertion)
- [x] Tests use snapshot testing where the full output is relevant
- [x] All tests pass
