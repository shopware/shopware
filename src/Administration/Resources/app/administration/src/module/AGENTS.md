# Module Layer - AGENTS.md

> **Full Docs**: `technical-docs/02-architecture/03-module-system.md`

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
  icon: 'solid-products',
  
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
      
      this.createNotificationSuccess({ message: this.$tc('sw.product.detail.messageSaved') });
    }
  }
};
```

## Snippets (i18n)

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

**Usage**: `this.$tc('sw.product.list.title')`


## Anti-Patterns

❌ Cross-module imports (modules must be independent)
❌ Missing ACL checks in routes/templates
❌ Not reloading after save
❌ Hardcoded strings (use `$tc()` for translations)
❌ Large components (split into views/components)
❌ Business logic in templates

**See**: `technical-docs/02-architecture/03-module-system.md`
