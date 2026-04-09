# Missing: Several Conversion Paths Have No Test Fixtures

**Status:** Implementation exists in `transform-script.ts` but no fixture exercises these paths, so correctness is unproven.

---

## Untested conversion paths

### 1. `this.$router` → `useRouter()`

```js
// Input
methods: {
    goBack() { this.$router.back(); }
}
```

Expected output:
```ts
import { useRouter } from 'vue-router';
const router = useRouter();
const goBack = () => { router.back(); };
```

No fixture or test exists for this.

---

### 2. `this.$route` → `useRoute()`

```js
// Input
computed: {
    currentId() { return this.$route.params.id; }
}
```

Expected output:
```ts
import { useRoute } from 'vue-router';
const route = useRoute();
const currentId = computed(() => route.params.id);
```

---

### 3. `this.$slots` → `useSlots()`

```js
// Input
computed: {
    hasContent() { return !!this.$slots.default; }
}
```

Expected output:
```ts
import { useSlots } from 'vue';
const slots = useSlots();
const hasContent = computed(() => !!slots.default);
```

---

### 4. `this.$nextTick` → `nextTick(...)`

```js
// Input
methods: {
    update() {
        this.value = 'new';
        this.$nextTick(() => { this.$refs.input.focus(); });
    }
}
```

Expected output:
```ts
import { nextTick } from 'vue';
const update = () => {
    value.value = 'new';
    nextTick(() => { input.value.focus(); });
};
```

---

### 5. `this.$t` / `this.$tc` → `useI18n().t` / `.tc`

```js
// Input
computed: {
    label() { return this.$tc('sw.button.label', 2); }
}
```

Expected output:
```ts
import { useI18n } from 'vue-i18n';
const { tc } = useI18n();
const label = computed(() => tc('sw.button.label', 2));
```

---

### 6. `this.$el` replacement output

```js
// Input
mounted() { this.$el.querySelector('.item').focus(); }
```

Expected output:
```ts
onMounted(() => { /* TODO: $el */ getCurrentInstance()?.proxy?.$el.querySelector('.item').focus(); });
```

There is no test verifying the replacement text is correctly inserted.

---

### 7. `inheritAttrs: false` → `defineOptions(...)`

```js
// Input
Shopware.Component.register('sw-foo', {
    inheritAttrs: false,
    // ...
});
```

Expected output:
```ts
defineOptions({ inheritAttrs: false });
```

---

### 8. `Shopware.Component.extend(...)` soft blocker

```js
Shopware.Component.extend('sw-foo-extended', 'sw-foo', {
    // ...
});
```

Expected: status `partially-migratable`, blockers `['extends']`, script type `'options'`.

This is a different soft blocker from `mixins` but uses the same backoff path — it should be tested separately.

---

### 9. `export default { ... }` normalization

```js
// Input (no Shopware.Component.register)
export default {
    data() { return { x: 1 }; },
};
```

This is handled by `normaliseJsContent` in the runner, but there is no test confirming the normalization produces valid input for the transform pipeline.

---

### 10. `{% extends %}` stripping in Twig

```twig
{% extends '@Administration/administration/...' %}
{% block sw_foo %}
<div>...</div>
{% endblock %}
```

`transform-template.ts` strips the `{% extends %}` line but there is no fixture for this. The `twig-comments.html.twig` fixture does not include an `{% extends %}` line.

---

### 11. File-system behaviors of `run-sfc-migration.ts`

See [runner-tests.unfinished.md](runner-tests.unfinished.md) for the full list of untested runner behaviors.

---

## How to add these tests

For each case above, create a new fixture file (or extend an existing one) in `__fixtures__/` and add a test in `transform-script.spec.ts` (or `transform-template.spec.ts` for the Twig case).

Fixture naming convention: `<feature>-component.index.js` + `<feature>-component.html.twig`.

For composable/router/i18n tests, a single fixture covering multiple of these (`$router`, `$route`, `$nextTick`, `$t`) in one component would be more efficient than seven separate fixtures.

---

## Acceptance check

- [ ] Each conversion path listed above has a dedicated test case (fixture + assertion)
- [ ] Tests use snapshot testing where the full output is relevant
- [ ] All tests pass
