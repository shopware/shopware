# PR Description — Written and Ready

**Status:** Description drafted and ready to paste into shopware/shopware#15673.

---

## How to apply

The token used by the GitHub MCP does not have write access to the upstream fork.
Copy the block below and paste it into the PR body editor at:
https://github.com/shopware/shopware/pull/15673

---

## PR Body (copy everything below this line)

---

### 1. Why is this change necessary?

Fixes https://github.com/shopware/shopware/issues/14681

### 2. What does this change do, exactly?

Adds a CLI codemod that automatically migrates Administration components from the traditional two-file format (`index.js` + `*.html.twig`) to a single `.vue` SFC. The script converts Options API to Composition API and Twig block syntax to `<sw-block>` elements.

#### Before

**`sw-block-card/index.js`**
```js
import template from './sw-block-card.html.twig';

Shopware.Component.register('sw-block-card', {
    template,

    inject: ['acl'],

    props: {
        readOnly: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['action'],

    data() {
        return {
            title: 'Block Card',
            count: 0,
        };
    },

    computed: {
        canEdit() {
            return !this.readOnly && this.acl.can('product.editor');
        },
    },

    methods: {
        onAction() {
            this.count += 1;
            this.$emit('action', this.count);
        },
    },
});
```

**`sw-block-card/sw-block-card.html.twig`**
```twig
{% block sw_block_card %}
<div class="sw-block-card">
    {% block sw_block_card_header %}
    <div class="sw-block-card__header">
        <h3>{{ title }}</h3>
    </div>
    {% endblock %}

    {% block sw_block_card_footer %}
    <div class="sw-block-card__footer">
        {{ parent() }}
        <button :disabled="!canEdit" @click="onAction">Action</button>
    </div>
    {% endblock %}
</div>
{% endblock %}
```

#### After

**`sw-block-card/sw-block-card.vue`**
```vue
<template>
    <sw-block name="sw_block_card" :data="$dataScope">
    <div class="sw-block-card">
        <sw-block name="sw_block_card_header" :data="$dataScope">
        <div class="sw-block-card__header">
            <h3>{{ title }}</h3>
        </div>
        </sw-block>

        <sw-block name="sw_block_card_footer" :data="$dataScope">
        <div class="sw-block-card__footer">
            <sw-block-parent/>
            <button :disabled="!canEdit" @click="onAction">Action</button>
        </div>
        </sw-block>
    </div>
    </sw-block>
</template>

<script setup>
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import { ref, computed, inject } from 'vue';

const props = defineProps({
    readOnly: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const emit = defineEmits(['action']);

const acl = inject('acl');

const {
    title,
    count,
    canEdit,
    onAction,
} = createExtendableSetup(
    { name: 'sw-block-card', props },
    () => {
        const title = ref('Block Card');
        const count = ref(0);

        const canEdit = computed(() => !props.readOnly && acl.can('product.editor'));

        const onAction = () => {
            count.value += 1;
            emit('action', count.value);
        };

        return {
            public: { title, count, canEdit, onAction },
        };
    },
);

const $dataScope = { title, count, canEdit, onAction };
</script>
```

#### Migration outcomes

| Outcome | Trigger | Result |
|---------|---------|--------|
| `fully-migratable` | No blockers | Full `<script setup>` with Composition API |
| `partially-migratable` | `mixins` or `Shopware.Component.extend()` | Plain `<script>` with original Options API kept; manual migration required |
| `not-migratable` | `render()` function | No `.vue` file written |

#### Options API → Composition API conversions handled

`data` → `ref`, `computed` (getter and getter+setter), `watch`, `methods`, all lifecycle hooks (`created`, `mounted`, `beforeUnmount`, etc.), `inject`, `props` → `defineProps`, `emits` → `defineEmits`, `inheritAttrs: false` → `defineOptions`.

`this` rewrites: `$emit` → `emit`, `$refs` → template refs, `$router`/`$route` → `useRouter()`/`useRoute()`, `$slots` → `useSlots()`, `$nextTick` → `nextTick`, `$attrs` → `useAttrs()`, `$t`/`$tc` → `useI18n()`, `$el` → flagged with `TODO` comment.

The `createExtendableSetup()` wrapper preserves Shopware's component extension mechanism (`overrideComponentSetup`) after migration.

#### Twig template conversions

| Twig | Vue |
|------|-----|
| `{% block name %}` | `<sw-block name="name" :data="$dataScope">` |
| `{% endblock %}` | `</sw-block>` |
| `{{ parent() }}` | `<sw-block-parent/>` |
| `{# comment #}` | `<!-- comment -->` |
| `{% extends '...' %}` | (line removed) |

#### How to run

```bash
# From src/Administration/Resources/app/administration/

# Dry-run preview (default — no files written)
npm run codemod:sfc-migration -- <target-directory>

# Write .vue files
npm run codemod:sfc-migration -- --write <target-directory>

# Write and delete source files afterwards
npm run codemod:sfc-migration -- --write --delete-originals <target-directory>

# Overwrite existing .vue files
npm run codemod:sfc-migration -- --write --force <target-directory>
```

#### Known limitations

- `this.$el` usages are replaced with a `TODO` comment and require manual refactoring to a template ref.
- Components using `mixins` or `Shopware.Component.extend()` are partially migrated (Options API kept) and need manual completion.

See `scripts/codemods/sfc-migration/README.md` for full usage documentation.

### 3. Describe each step to reproduce the issue or behaviour.

Run the codemod in dry-run mode against any Administration component directory:

```bash
cd src/Administration/Resources/app/administration
npm run codemod:sfc-migration -- src/module/sw-product/component/sw-product-basic-form
```

The output lists each component with its migration status (`✓` fully migrated, `~` partially migrated, `✗` not migratable) and a summary. Pass `--write` to actually produce `.vue` files.

### 4. Please link to the relevant issues (if any)

https://github.com/shopware/shopware/issues/14681

### 5. Checklist

- [x] I have written tests and verified that they fail without my change
- [ ] I have updated developer-facing release notes if this change is **relevant** for external developers:
  - Add a short entry to `RELEASE_INFO-6..md` under "Upcoming" for informational changes, including the consequences of the change and how it affects external developers.
  - Add an `UPGRADE` section in `UPGRADE-6..md` for breaking changes (what/why/impact/how to adapt).
  - See the [Documenting a Release Process](https://github.com/shopware/shopware/blob/trunk/delivery-process/documenting-a-release.md) for details.
- [x] I have written or adjusted the documentation according to my changes
- [ ] This change has comments for package types, values, functions, and non-obvious lines of code
- [x] I have read the contribution requirements and fulfilled them
