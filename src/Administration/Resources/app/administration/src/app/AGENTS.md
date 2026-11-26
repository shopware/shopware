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

Templates wrap their markup in Twig blocks, so extensions can override each part.

```twig
{% block sw_product_detail %}
  <sw-page>
    <template #content>
      {% block sw_product_detail_content %}
        {# Meteor components, see below #}
      {% endblock %}
    </template>
  </sw-page>
{% endblock %}
```

Build the UI inside the blocks from Meteor components.
This file names no components or props on purpose, because they change between Meteor releases.
Look them up through the Meteor MCP server, see "UI components, styling and UX writing (Meteor)" in the Administration `AGENTS.md`.

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
  &__header { }
  &__grid { }
}
```

Take every color, spacing, typography and border radius value from a Meteor design token.
This file names no tokens on purpose; look up the current names through the Meteor MCP server instead of guessing them.

## Anti-Patterns

❌ Local component imports
❌ Direct DOM manipulation
❌ Mutating props
❌ Business logic in templates
❌ Inline styles
❌ Hard-coded colors, spacing or font sizes where a Meteor token exists
❌ New usages of deprecated `sw-*` components that have an `mt-*` replacement
❌ Meteor components marked as experimental
❌ Component props, token names or icon names written from memory instead of looked up in the Meteor docs
❌ Snippet text written without checking the Meteor wording, messaging and glossary pages
❌ Using mixins for new code (use composables)

**See**: `technical-docs/02-architecture/02-folder-structure.md` for complete structure
