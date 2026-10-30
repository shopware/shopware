# Module Layer - AGENTS.md

> **Full Docs**: `technical-docs/02-architecture/03-module-system.md`
> **Skill**: Module work → follow `shopware-admin-js` (`.agents/skills/shopware-admin-js/SKILL.md`).

## Module Structure

```
sw-[module]/
├── index.js          # Module registration (REQUIRED)
├── acl/index.js      # Privileges
├── page/             # List/detail pages
├── view/             # Detail tabs
├── component/        # Module components
├── snippet/          # Translations
└── default-search-configuration.js
```

## Mini-Modules

A mini-module is a directory that works like a small library, with a public API and private code behind it:

```
order-delivery/
├── index.ts                  # The public API, for other Administration code and for extensions, every export marked @public
├── private.ts                # Optional: an API for Administration code only, every export marked @private
├── src/                      # The implementation, imported only by index.ts, private.ts and src/ itself, no markers
└── tests/
    ├── requirements.spec.ts  # What someone decided, from the user's point of view
    ├── pinned.spec.ts        # The details and edge cases, as they behave today
    ├── regressions.spec.ts   # Bugs that must not come back
    └── *.spec.ts             # Tests of internals, which should be rare
```

Import a mini-module through its directory, which resolves to `index.ts`.
An import of `.../private` from outside the module fails the lint unless the line disables the rule and says why, so every use of a private API shows up in review.
Every export of `index.ts` is marked `@public`, and changes to it follow the deprecation process.
Every export of `private.ts` is marked `@private`, and `private.ts` never reaches extensions.
Exports in `src/` carry no marker, because the location already says they are private.
A marker anywhere else in a mini-module means the export is in the wrong file, and `@public` outside a mini-module's `index.ts` is not allowed at all.

A component in a mini-module declares itself in `index.ts` and registers in `register.ts`, the only file with side effects:

```ts
// index.ts: name, lazy loader and the complete public API
/** @public */
export default definePublicComponent({
    name: 'sw-example',
    load: () => import('./src/sw-example'),
    public: { props: ['value'], emits: ['update:value'], blocks: ['sw_example'], methods: ['save'] },
});

// register.ts
import SwExample from './index';

Shopware.Component.register(SwExample);
```

Anything the `public` lists leave out is private, and a meta test checks that every listed name exists.
A native-setup component leaves out `public`, because `swDefinePublic()` declares it.
`src/` stays free of side effects apart from importing its template and styles.

Requirement tests stay rough.
They describe what a merchant expects, in a few tests whose titles read like sentences, and each one has a `@requirement` doc comment that says why.
Details and edge cases go into pinned tests.
They need no comment, and an agent may write them by freezing the current behaviour.
Each regression test has a `@regression` doc comment with the issue and what users saw.
A test that the next major removes (`it.deprecated(...)`) also has `@deprecated tag:vX.Y.Z` in its doc comment, in every kind of spec file.

Requirements, pinned and regressions specs import the module only through `../index`, so they test what other code sees.
A change to `index.ts`, a requirement or a regression needs the owning team (`.codeowners`).
A changed pin only asks whether the change was intended.

Put a requirement into the smallest module that owns it, and let the code that uses the module rely on its requirements.
Tests describe the code as it is now, so delete an outdated test and give the reason in the commit instead of skipping it.
`sw-core-rules/mini-module-boundaries` and `sw-test-rules/require-test-origin` check these rules.

## Registration Pattern

```js
// 1. Register components (lazy-loaded)
Shopware.Component.register('sw-product-list', () => import('./page/sw-product-list'));
Shopware.Component.register('sw-product-detail', () => import('./page/sw-product-detail'));

// 2. Register module
Module.register('sw-product', {
  type: 'core',
  name: 'product',
  entity: 'product',
  title: 'sw-product.general.mainMenuItemGeneral',
  color: '#57D9A3',
  icon: 'regular-products',

  routes: {
    index: {
      component: 'sw-product-list',
      path: 'index',
      meta: { privilege: 'product.viewer' }
    },
    detail: {
      component: 'sw-product-detail',
      path: 'detail/:id?',
      meta: { privilege: 'product.viewer' },
      children: {
        base: {
          component: 'sw-product-detail-base',
          path: 'base'
        }
      }
    }
  },

  navigation: [{
    id: 'sw-product',
    label: 'sw-product.general.mainMenuItemGeneral',
    path: 'sw.product.index',
    parent: 'sw-catalogue',
    privilege: 'product.viewer',
    position: 10
  }]
});
```

## ACL Configuration

```js
Shopware.Service('privileges').addPrivilegeMappingEntry({
  category: 'permissions',
  parent: 'catalogues',
  key: 'product',

  roles: {
    viewer: {
      privileges: ['product:read', 'manufacturer:read'],
      dependencies: []
    },
    editor: {
      privileges: ['product:update'],
      dependencies: ['product.viewer']
    },
    creator: {
      privileges: ['product:create'],
      dependencies: ['product.viewer', 'product.editor']
    },
    deleter: {
      privileges: ['product:delete'],
      dependencies: ['product.viewer']
    }
  }
});
```

## Page Patterns

### List Page
```ts
export default {
  inject: ['repositoryFactory', 'acl'],
  mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

  computed: {
    repository() {
      return this.repositoryFactory.create('product');
    },

    criteria() {
      const criteria = new Criteria(this.page, this.limit);
      criteria.setTerm(this.term);
      criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
      return criteria;
    }
  },

  methods: {
    async getList() {
      this.isLoading = true;
      this.items = await this.repository.search(this.criteria, Shopware.Context.api);
      this.total = this.items.total;
      this.isLoading = false;
    }
  }
};
```

### Detail Page
```ts
export default {
  inject: ['repositoryFactory', 'acl'],
  mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

  computed: {
    repository() {
      return this.repositoryFactory.create('product');
    },
    ...mapPropertyErrors('product', ['name', 'price'])
  },

  methods: {
    async loadEntity() {
      const criteria = new Criteria();
      criteria.addAssociation('manufacturer');

      this.entity = await this.repository.get(this.entityId, Shopware.Context.api, criteria);
    },

    async onSave() {
      await this.repository.save(this.entity, Shopware.Context.api);

      // ✅ CRITICAL: Reload to sync origin for change tracking
      this.entity = await this.repository.get(this.entity.id, Shopware.Context.api);

      this.createNotificationSuccess({ message: this.$t('sw.product.detail.messageSaved') });
    }
  }
};
```

## Snippets (i18n)

German (`de-DE`) Administration snippets use the informal "Du" (unlike the Storefront, which uses "Sie").

```json
{
  "sw-product": {
    "general": {
      "mainMenuItemGeneral": "Products"
    },
    "list": {
      "title": "Products",
      "buttonCreate": "Add product"
    },
    "detail": {
      "labelName": "Name",
      "messageSaved": "Product saved successfully"
    }
  }
}
```

**Usage**: `this.$t('sw.product.list.title')`


## Anti-Patterns

❌ Cross-module imports (modules must be independent)
❌ Missing ACL checks in routes/templates
❌ Not reloading after save
❌ Hardcoded strings (use `$t()` for translations)
❌ Large components (split into views/components)
❌ Business logic in templates

**See**: `technical-docs/02-architecture/03-module-system.md`
