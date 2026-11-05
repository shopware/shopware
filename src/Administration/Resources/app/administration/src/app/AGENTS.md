# App Layer - AGENTS.md

> **Full Docs**: `technical-docs/02-architecture/` for boot process, folder structure, state management

## Critical Patterns

### Boot Sequence (Order Matters!)
```
init-pre/ → init/ → init-post/
```
**See**: `src/app/init/AGENTS.md` for details

### Dependency Injection (NOT imports)
```ts
// ✅ CORRECT
inject: ['repositoryFactory', 'acl']

// ❌ WRONG
import repositoryFactory from '...';
```

### Global Components Only
```ts
// ✅ Registered globally in init/component.init.ts
Shopware.Component.register('sw-product-list', () => import('./page'));

// ❌ Local imports break plugin system
import SwProductList from './page';
```

## Directory Overview

- **`init/`**: Boot sequence (See AGENTS.md)
- **`component/`**: Global UI components (See AGENTS.md)
- **`store/`**: Pinia stores (See AGENTS.md)
- **`composables/`**: Vue 3 hooks (use-context, use-session, use-system). Mixin replacements listed in `composables/index.ts` are public as `Shopware.Composables` and `shopware:composables`
- **`mixin/`**: Legacy shared logic (prefer composables)
- **`assets/scss/`**: Global styles, variables, mixins
- **`snippet/`**: Translations (de.json, en.json)

## Component Development

New components are TypeScript, wrapped in `wrapComponentConfig()` and use composables instead of mixins.
The complete reference with spec is `src/app/component/media/sw-media-folder-thumbnail/`.

```ts
import type RepositoryType from 'src/core/data/repository.data';
import useNotification from 'src/app/composables/use-notification';
import template from './sw-product-name-card.html.twig';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default Shopware.Component.wrapComponentConfig({
  template,

  inject: ['repositoryFactory', 'acl'],

  props: {
    productId: {
      type: String,
      required: true,
    },
  },

  data(): { product: Entity<'product'> | null } {
    return {
      product: null,
    };
  },

  computed: {
    productRepository(): RepositoryType<'product'> {
      return this.repositoryFactory.create('product');
    },

    ...mapPropertyErrors('product', ['name']),
  },

  methods: {
    async save(): Promise<void> {
      if (!this.product) {
        return;
      }

      await this.productRepository.save(this.product, Shopware.Context.api);
      useNotification().createNotificationSuccess({
        message: this.$t('sw-product.detail.messageSaveSuccess', { name: this.product.name }),
      });
      this.product = await this.productRepository.get(this.productId, Shopware.Context.api);
    },
  },
});
```

## Template Patterns (TwigJS)

```twig
{% block sw_product_detail %}
  <sw-page>
    <template #content>
      <sw-card position-identifier="sw_product_detail_base">
        <mt-text-field v-model="product.name" />
      </sw-card>
    </template>
  </sw-page>
{% endblock %}
```

## State Management

```ts
// Register
Shopware.Store.register({ id: 'myStore', state, actions, getters });

// Access
const store = Shopware.Store.get('myStore');
```

**See**: `src/app/store/AGENTS.md` for patterns

## Styling (BEM + Meteor Tokens)

```scss
.sw-product-list {
  padding: var(--mt-spacing-4);
  color: var(--mt-color-text-primary);

  &__header { }
  &__grid { }
}
```

## Anti-Patterns

❌ Local component imports
❌ Direct DOM manipulation
❌ Mutating props
❌ Business logic in templates
❌ Inline styles
❌ Using mixins for new code (use composables)

**See**: `technical-docs/02-architecture/02-folder-structure.md` for complete structure
