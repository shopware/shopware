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
├── src/                      # The implementation, imported only by index.ts, private.ts and src/ itself
└── tests/
    ├── requirements.spec.ts  # What someone decided, from the user's point of view
    ├── pinned.spec.ts        # The details and edge cases, as they behave today
    ├── regressions.spec.ts   # Bugs that must not come back
    ├── public-api.spec.ts    # Optional: public members that no user path reaches, as they behave today
    ├── .gitkeep              # Only while tests/ has no spec, so git keeps the folder
    └── *.spec.ts             # Tests of internals, which should be rare
```

Import a mini-module through its directory, which resolves to `index.ts`.
An import of `.../private` from outside the module fails the lint unless the line disables the rule and says why, so every use of a private API shows up in review.
Every export of `index.ts` is marked `@public`, and changes to it follow the deprecation process.
Every export of `private.ts` is marked `@private`, and `private.ts` never reaches extensions.
Exports in `src/` and `dependencies.ts` carry a marker too, so the source shows what is exposed: `@public` when `index.ts` exposes the export, `@private` otherwise.
Outside mini-modules, `@public` is not allowed at all.

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
A native-setup component (`load: () => import('./src/sw-example.vue')`) lists all its props, emits, slots and blocks, and its `swDefinePublic()` bindings under `setup`:

```ts
public: { props: ['label'], emits: ['save'], slots: ['default'], blocks: ['sw_example'], setup: ['count', 'save'] },
```

Nothing hides a prop, emit, slot or block of a native-setup component, so all of them are public.
The setup transform compares these lists with the SFC on every build and Jest run, and fails on any name only one side has.
TypeScript checks the declaration against an Options API component, also against a JavaScript one:
a name in `public` the component doesn't have is a type error.
`PublicInstance<typeof SwExample>` is the type of the public members, for a parent's template ref or a test.
`global.d.ts` next to `index.ts` adds the component to the global `PublicComponents` interface, so `Shopware.Component.override()` on that name may replace public members only, and `this.$super()` takes public names only.
Extensions see these types through `extension-tooling/mini-modules.d.ts`, which imports every `global.d.ts`; a spec fails when one is missing there.

Extensions import a mini-module as `shopware:<kind>/<directory name>`, so a component module's directory carries the component name.
A component module is of the kind `components`.
Any other mini-module names its kind with `@shopware-kind <kind>` in the doc comment of its `index.ts` exports, and the generator fails without one:

```ts
// index.ts of a helper module
/**
 * @public
 * @shopware-kind helpers
 */
export { getPrimaryDelivery } from './src/primary-delivery';
```

The kinds are `components`, `framework`, `handlers` and `helpers`; `PUBLIC_KINDS` in `src/core/mini-module-registry.ts` lists them.
`register.ts` publishes the exports of `index.ts` for that import under `Shopware.Public.<kind>`, and every mini-module has one, also without a component:

```ts
// register.ts
import * as SwExample from './index';

Shopware._private.publish('components', 'sw-example', SwExample);
```

Run `composer admin:generate-shopware-modules` after adding a mini-module or changing the exports of its `index.ts`.
A spec fails when a `register.ts` publishes anything else, or when the generated files are stale.

```ts
// global.d.ts
import type SwExample from './index';

declare global {
    interface PublicComponents {
        'sw-example': typeof SwExample;
    }
}
```

A component that extends another one names it with `extends: 'sw-base'`, and its `register.ts` calls `Shopware.Component.extend(SwExample)`.
Its `public` lists include what it inherits, because an override reaches that too; the meta test checks those names against the base.

A component that its parent imports and lists in `components` has no global name and stays that way.
Its `private.ts` exports the loaded component for the parent, its `register.ts` only publishes the module, and it has no `global.d.ts`.

`npm run codemod:mini-module -- <component directory>` does the mechanical part of moving an Options API component into a mini-module,
whether it is registered, extends another component, or is imported by its parent.
It takes the code owner from what `.codeowners` already says about the directory, at the same level, and never guesses one.
It prints a checklist of what is left for the sweep after it: `dependencies.ts`, contract tests, a missing code owner.

`src/` stays free of side effects apart from importing its template and styles.

Everything `src/` needs from outside the module comes through `dependencies.ts`, the inward counterpart of `index.ts`:

```ts
// dependencies.ts
export { default as Criteria } from 'src/core/data/criteria.data';
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
export const getApiContext = () => Shopware.Context.api;
```

`src/` never mentions the `Shopware` global and imports nothing from outside the module except `../dependencies`.
Anything that has a module to import is imported, not looked up by a written name.
Where a name stays necessary, like `inject`, the name is imported from the module that owns it.
`dependencies.ts` has no side effects: anything that must run, or read state that changes, is a function that runs when it is used.

Requirement tests stay rough, and `requirements.spec.ts` is the first file a newcomer reads, so it tells the module's story.
A comment above the `describe` says in two to four sentences what the module is for and where its things come from.
The tests follow in the order a user meets them, the everyday case first and the safety nets last, each title a plain sentence about what someone can do or what they see.
Each test has a `@requirement` doc comment that says why, from the point of view of the people who use the module.
Details and edge cases go into pinned tests.
They need no comment, and an agent may write them by freezing the current behaviour.
Each regression test has a `@regression` doc comment with the issue and what users saw.

A public member that no user path reaches, such as a computed that only an override could read, is still API.
Pin it in `public-api.spec.ts`, the only spec that may read the component through `wrapper.vm`.
It may read or call only the props, computed values and methods that the `public` lists of `index.ts` name, so it cannot pin a private detail:

```ts
/** @public-api usedRules */
it('lists the rules the price matrices use', async () => {
    // ARRANGE
    const wrapper = await mountPriceMatrices();

    // ACT
    const usedRules = wrapper.vm.usedRules;

    // ASSERT
    expect(usedRules).toEqual(['rule-1']);
});
```

Each of its tests names the member it pins in a `@public-api` doc comment, because a change to that behaviour can break an extension.

A test that the next major removes (`it.deprecated(...)`) also has `@deprecated tag:vX.Y.Z` in its doc comment, in every kind of spec file.

Every test in these contract files marks its steps with comments, so a reader sees at once what is set up, what runs and what is checked:

```ts
it('shows an order with its primary delivery', () => {
    // ARRANGE
    const order = createOrder({ primaryOrderDelivery: primary });

    // ACT
    const shown = getPrimaryDelivery(order);

    // ASSERT
    expect(shown).toBe(primary);
});
```

`// ARRANGE` may be left out when nothing needs setting up.
`sw-test-rules/arrange-act-assert` checks the comments and their order.

Requirements, pinned, regressions and public-api specs import the module only through `../index`, so they test what other code sees.
They may arrange state through a harness in `tests/`, and the harness may import from `../dependencies`.
A change to `index.ts`, a requirement or a regression needs the owning team.
`mini-module-owners.json` names the owner of each mini-module, with `? ` in front when the team is only optional, as in `.codeowners`.
`composer admin:generate-mini-module-owners` writes the mini-modules block of `.codeowners` from it, and a spec fails when the block is stale.
A changed pin only asks whether the change was intended.
A change to `public-api.spec.ts` needs the owning team as well, because extensions rely on what those members do.

Put a requirement into the smallest module that owns it, and let the code that uses the module rely on its requirements.
Tests describe the code as it is now, so delete an outdated test and give the reason in the commit instead of skipping it.
A contract spec that grows past 500 lines splits into a directory of the same name, such as `tests/pinned.spec/search.spec.ts`.
`sw-core-rules/mini-module-boundaries`, `sw-test-rules/require-test-origin` and `sw-test-rules/no-vm-access` check these rules.

`npm run unit:mini-module -- <module directory>` runs one module's tests with coverage of its `src/` only.
Add `--contracts` to run only the contract specs (requirements, pinned, regressions, public-api): the uncovered lines are then code that no contract protects.

`npm run unit:affected` runs the specs that the changes since `origin/trunk` (or `--base <ref>`, or the files given) can break, and `--dry-run` prints them with the reason for each.
A change inside a mini-module selects its own specs, plus the specs that mount its component by name, plus the importers of `index.ts` or `private.ts` when they change.
A module without a component has no name to mount by, so a change in its `src/` or `dependencies.ts` selects the importers of `index.ts` and `private.ts` instead.
Outside mini-modules it takes Jest's import graph plus every spec that mentions a name the file registers, and warns when that cannot be enough, for example for code the Jest setup loads.
In the container of a git worktree, git does not work, so take the changed files from the host: `npm run unit:affected -- $(npm run -s unit:affected -- --changed-files)`, with the inner command run on the host.

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

Pages follow the component rules from `src/app/AGENTS.md`: TypeScript, `wrapComponentConfig()` and composables instead of mixins.

### List Page

The `listing` mixin stays on Options API list pages, because `useListing()` only works inside `setup()` and can't call back into a `getList()` method there.

```ts
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-product-list.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default Shopware.Component.wrapComponentConfig({
  template,

  inject: ['repositoryFactory', 'acl'],

  mixins: [Mixin.getByName('listing')],

  data(): { products: EntityCollection<'product'> | null; isLoading: boolean } {
    return {
      products: null,
      isLoading: false,
    };
  },

  computed: {
    productRepository(): RepositoryType<'product'> {
      return this.repositoryFactory.create('product');
    },

    productCriteria(): CriteriaType {
      const criteria = new Criteria(this.page, this.limit);
      criteria.setTerm(this.term);
      criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

      return criteria;
    },
  },

  methods: {
    async getList(): Promise<void> {
      this.isLoading = true;
      this.products = await this.productRepository.search(this.productCriteria, Shopware.Context.api);
      this.total = this.products.total ?? 0;
      this.isLoading = false;
    },
  },
});
```

### Detail Page

```ts
import type RepositoryType from 'src/core/data/repository.data';
import useNotification from 'src/app/composables/use-notification';
import template from './sw-product-detail.html.twig';

const { Criteria } = Shopware.Data;
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

    ...mapPropertyErrors('product', ['name', 'price']),
  },

  methods: {
    async loadProduct(): Promise<void> {
      const criteria = new Criteria();
      criteria.addAssociation('manufacturer');

      this.product = await this.productRepository.get(this.productId, Shopware.Context.api, criteria);
    },

    async onSave(): Promise<void> {
      if (!this.product) {
        return;
      }

      await this.productRepository.save(this.product, Shopware.Context.api);

      useNotification().createNotificationSuccess({
        message: this.$t('sw-product.detail.messageSaveSuccess', { name: this.product.name }),
      });

      // ✅ CRITICAL: Reload to sync origin for change tracking
      await this.loadProduct();
    },
  },
});
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
