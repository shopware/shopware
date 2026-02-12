# Issue 08: Migrate Core Component Logic to Composition API

**Phase:** 2 — Migration Wave
**Priority:** High
**Estimate:** ~2–4 hours per component (~600 components)
**Labels:** `migration`, `composition-api`, `component-migration`

---

## Summary

Migrate all core administration components from Options API (`data`, `computed`, `methods`, `watch`, `inject`, `mixins`) to Composition API using `setup()` with `createExtendableSetup()`. This is the largest effort in the epic and must preserve the public API surface for each component.

---

## Problem

The current administration uses ~600+ components registered via `Component.register()` with Options API configuration. This pattern:

- Prevents effective TypeScript adoption (Options API type inference is limited)
- Uses mixins which cause naming collisions and unclear dependency chains
- Uses `$super()` chain mechanism that is specific to Shopware and not standard Vue
- Limits code reuse patterns (mixins vs. composables)
- Makes components harder to tree-shake

---

## Acceptance Criteria

- [ ] Every `data()` property becomes a `ref()` or `reactive()` with the **same name** in the public API
- [ ] Every `computed` property becomes a `computed()` with the **same name**
- [ ] Every `methods` entry becomes a function with the **same name and signature**
- [ ] Every `inject` dependency remains available in the public API surface
- [ ] Mixins are replaced by composables (see migration table below)
- [ ] `createExtendableSetup()` is used for every migrated component so plugins can override via `overrideComponentSetup()`
- [ ] Public API surface matches the previous Options API surface exactly (property/method names)
- [ ] Each migrated component passes the extension integration test suite (Issue #05)
- [ ] TypeScript types are correct for the component's public API
- [ ] Component renders and behaves identically after migration

---

## Technical Approach

### Migration Pattern per Component

**Before (Options API):**
```javascript
Shopware.Component.register('sw-example', {
    inject: ['repositoryFactory', 'acl'],
    mixins: [Mixin.getByName('notification')],
    
    data() {
        return {
            product: null,
            isLoading: false,
        };
    },
    
    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },
    },
    
    methods: {
        async loadProduct(id) {
            this.isLoading = true;
            this.product = await this.productRepository.get(id);
            this.isLoading = false;
        },
    },
});
```

**After (Composition API):**
```javascript
Shopware.Component.register('sw-example', {
    setup(props) {
        return createExtendableSetup({ name: 'sw-example', props }, () => {
            const repositoryFactory = inject('repositoryFactory');
            const acl = inject('acl');
            const { createNotificationSuccess } = useNotification(); // replaces mixin

            const product = ref(null);
            const isLoading = ref(false);

            const productRepository = computed(() => repositoryFactory.create('product'));

            const loadProduct = async (id) => {
                isLoading.value = true;
                product.value = await productRepository.value.get(id);
                isLoading.value = false;
            };

            return {
                public: {
                    product,
                    isLoading,
                    productRepository,
                    loadProduct,
                    // Exposed for plugin overrides
                },
                private: {
                    repositoryFactory,
                    acl,
                    createNotificationSuccess,
                },
            };
        });
    },
});
```

### Mixin → Composable Migration Table

| Mixin | Composable Replacement |
|-------|----------------------|
| `notification` | `useNotification()` |
| `listing` | `useListing()` |
| `validation` | `useValidation()` |
| `form-field` | `useFormField()` |
| `placeholder` | `usePlaceholder()` |
| `salutation` | `useSalutation()` |
| `sw-inline-snippet` | `useInlineSnippet()` |
| `position` | `usePosition()` |
| `remove-api-error` | `useRemoveApiError()` |
| `cart-notification` | `useCartNotification()` |
| `discount-type` | `useDiscountType()` |
| `user-settings` | `useUserSettings()` |

### Public API Preservation Rules

1. Every `data()` property → `ref()` with **same name**
2. Every `computed` property → `computed()` with **same name**
3. Every `methods` entry → function with **same name and signature**
4. Every `inject` → keep available (public or private API depending on usage)
5. Document the mapping: `this.product` → `previousState.product` (auto-unwrapped in templates)

### Existing ESLint Rule

The `eslint-rules/deprecation-rules/no-vue-options-api.js` rule detects and can auto-fix many Options API patterns. Use it as a starting point for each migration, then manually handle complex cases.

---

## Sub-Tasks (Suggested Batching)

Like the template migration (Issue #07), this can be batched per module:

| Module | Approximate Component Count |
|--------|----------------------------|
| `sw-product` | ~60 components |
| `sw-order` | ~45 components |
| `sw-customer` | ~30 components |
| `sw-settings` | ~80 components |
| `sw-cms` | ~50 components |
| `sw-media` | ~25 components |
| Shared components | ~100 components |
| Other modules | ~210 components |

### Migration Order Recommendation

1. **Composables first**: Create all composable replacements for mixins before starting component migration
2. **Leaf components** (no children, simple logic) — validate the process
3. **Shared components** — unlock module-level migration
4. **Module pages** — complex but high impact
5. **Complex components** — components with many overrides, deep mixin usage

---

## Testing Requirements

- [ ] Each migrated component renders identically (visual + functional)
- [ ] Extension integration tests pass (Scenarios 1, 2, 3, 6 from Issue #05)
- [ ] Options API override via shim (Issue #01) works on the migrated component
- [ ] Composition API override (`overrideComponentSetup`) works correctly
- [ ] TypeScript compiles without errors
- [ ] No console errors or Vue warnings

---

## Risks & Open Questions

- **Public/private API boundary**: For each component, the team must decide what goes in `public` (overridable by plugins) vs. `private`. This needs guidelines. A reasonable default: everything that was accessible via `this.xxx` in Options API should be `public`.
- **Mixin extraction complexity**: Some mixins have complex interactions with the host component (e.g., accessing `this.someData` that only exists on the host). The composable replacement may need to accept parameters.
- **Component.extend() compatibility**: Components created via `Component.extend()` need to also work with the migrated base component. Test this for each affected component.
- **Parallel vs. sequential with template migration**: Can logic and template migration be done in the same PR per component? Yes, if compatibility shims (Issues #01, #02) are in place. Otherwise, do templates first (see analysis §5.1).
