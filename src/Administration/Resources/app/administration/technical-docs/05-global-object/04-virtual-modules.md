# `shopware:*` Modules

Status: Stable

The `shopware:*` modules expose selected parts of the global `Shopware` object as ES modules. The global
object remains available, and existing `Shopware.*` calls continue to work.

```ts
const globalId = Shopware.Utils.createId();

import { createId } from 'shopware:utils';
const importedId = createId();
```

## Available modules

`shopware:utils` and `shopware:data` provide default and named exports for their global branches.

```ts
import utils, { createId, debounce } from 'shopware:utils';
import data, { Criteria } from 'shopware:data';
```

Each member also has a subpath with a default export. Utility namespaces can have explicit named
exports. For example, `shopware:utils/debug` provides `warn` and `error`.

```ts
import debug, { warn } from 'shopware:utils/debug';
import EventBus from 'shopware:utils/EventBus';
import CriteriaClass from 'shopware:data/Criteria';
```

`shopware:mixins` and `shopware:stores` only have subpaths. The subpath matches the registry key.

```ts
import swFormFieldMixin from 'shopware:mixins/sw-form-field';
import useSwOrderDetailStore from 'shopware:stores/swOrderDetail';
```

| Specifier                 | Exports                                                      |
| ------------------------- | ------------------------------------------------------------ |
| `shopware:utils`          | `Shopware.Utils` as default and its members as named exports |
| `shopware:utils/<member>` | The member as default, plus declared namespace exports       |
| `shopware:data`           | `Shopware.Data` as default and its classes as named exports  |
| `shopware:data/<Class>`   | The class as default                                         |
| `shopware:mixins/<name>`  | The registered mixin as default                              |
| `shopware:stores/<id>`    | A store composable as default                                |

A store subpath returns a composable that defers the registry lookup until the composable runs. Store
subpaths have no named exports because destructuring a Pinia store removes reactivity. Use `storeToRefs`
when a component needs reactive properties from the store.

The mixin and store families have individual subpaths and no root module.

## Resolution timing

Production imports of utilities, data classes, and mixins capture their initial values when the module
evaluates. A store module captures the global object but defers its registry lookup until its exported
function runs.

A mixin must exist before its subpath module evaluates. Import mixins from components that load after
the Administration boot process. Early module initialization must continue to use
`Shopware.Mixin.getByName()` or import the mixin implementation first.

The global object must also exist before a `shopware:*` module evaluates. `src/index.ts` creates it
before it imports `src/app/main`, so application and extension code can use these modules. Bootstrap code
must keep its existing access paths and defer global access until the object exists. This includes
`src/core/**` and eager `import.meta.glob` targets reached by static imports from `src/index.ts`.

The checked-in registry only contains Administration registrations. An extension cannot import a store
or mixin that the extension registers at runtime. Use the extension's own composable,
`Shopware.Store.get()`, or `Shopware.Mixin.getByName()` for these registrations.

## Generated files

Run this command after you add a utility, DAL class, mixin, or store:

```bash
composer admin:generate-shopware-modules
```

The command reads the existing source contracts and writes these files:

- `shopware-modules.json` lists each valid specifier and its named exports.
- `src/shopware-virtual-modules.d.ts` declares the same default and named exports for TypeScript.

Both files are checked in. The generator test fails when either file does not match the source.

## Build and test integration

The Vite plugin in `build/vite-plugins/virtual-shopware-modules` generates one ES module for each valid
specifier. It emits static named exports so Rollup can reject invalid imports and remove unused exports.
A pure annotation lets Rollup remove an unused mixin lookup.

Jest does not run Vite. The Jest configuration writes all current stub files before its workers start.
Unlike production bindings, named stub exports use getters. Tests that replace `window.Shopware` receive
values from their mocks. Store stubs also read the replaced global object on each call.
