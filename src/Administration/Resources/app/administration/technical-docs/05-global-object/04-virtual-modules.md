# `shopware:*` Modules

Status: Experimental, stable with v6.8.0

The set of specifiers, what each one exports, and their types can change in any release until then, with
no deprecation cycle. `Shopware.*` access is stable and unaffected.

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
| `shopware:mixins/<name>`  | The registered mixin as default, for mixins in `src/app/mixin` |
| `shopware:stores/<id>`    | A store composable as default                                |

A store subpath returns a composable that defers the registry lookup until the composable runs. Store
subpaths have no named exports because destructuring a Pinia store removes reactivity. Use `storeToRefs`
when a component needs reactive properties from the store.

The mixin and store families have individual subpaths and no root module.

## Resolution timing

Production imports of utilities, data classes, and mixins capture their initial values when the module
evaluates. A store module captures the global object but defers its registry lookup until its exported
function runs.

A generated module does not read a global in the Administration build. It imports the instance from
`src/core/shopware`, so evaluation order guarantees the object exists, and a `shopware:*` import is safe
at any point in the boot sequence. A mixin subpath additionally imports `src/app/mixin`, whose eager glob
registers every mixin in that directory, so the lookup cannot run before registration.

An extension bundle has no Administration source to import and reads the global instead. Extension code
runs after the Administration has booted, so the object is always there; the generated module throws with
that explanation if it ever is not.

`src/core` keeps using the global as a matter of layering. It is the Vue-independent framework code, it
boots first, and part of it is bundled into the admin worker, where there is no `window.Shopware` at all.
Files under `src/app/mixin` keep it too, because a generated mixin module imports that directory and the
reverse would be a cycle.

A mixin a feature module registers as it loads — `cms-element` and `cms-state` from `sw-cms`, for
instance — has no subpath, because nothing can guarantee it is registered when an importer evaluates.
Those keep `Shopware.Mixin.getByName()`.

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
