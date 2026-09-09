# `shopware:*` Modules

Status: Stable

Everything an extension needs is reachable through the global `Shopware` object, and that will not change.
The `shopware:*` modules add a second way to reach the same object, so the code that uses it reads like
ordinary ES module code:

```ts
// Both lines do the same thing.
const id = Shopware.Utils.createId();

import { createId } from 'shopware:utils';
const id = createId();
```

The modules are purely additive. Every export is the very object the global holds, so component
overrides, the plugin boot order, and every existing `Shopware.*` call keep working unchanged. Nothing
has to be migrated, and both styles can appear in the same file.

## Available modules

`shopware:utils` and `shopware:data` publish a branch's members. Their members are also available one at
a time, which is what you want when you would otherwise destructure:

```ts
import { createId, debounce } from 'shopware:utils';
import { warn } from 'shopware:utils/debug';
import { Criteria } from 'shopware:data';
```

`shopware:mixins` and `shopware:stores` are subpath-only. The subpath is the registry key, verbatim:

```ts
import swFormFieldMixin from 'shopware:mixins/sw-form-field';
import cmsElementMixin from 'shopware:mixins/cms-element';
import useSwOrderDetailStore from 'shopware:stores/swOrderDetail';
```

| Specifier | Publishes |
| --- | --- |
| `shopware:utils` | every member of `Shopware.Utils` |
| `shopware:utils/<member>` | that member as the default export, plus its own names where it is an object |
| `shopware:data` | every class on `Shopware.Data` |
| `shopware:data/<Class>` | that class as the default export |
| `shopware:mixins/<name>` | the registered mixin as the default export |
| `shopware:stores/<id>` | a composable returning the store, as the default export |

A store subpath never publishes named exports. Destructuring a Pinia store drops reactivity, which is
what `storeToRefs` exists for.

There is no `shopware:mixins` or `shopware:stores` barrel. A barrel over a runtime registry has to
resolve every entry the moment anything imports it, and the development server serves it unbundled, so
one importer would depend on all of them being registered. A subpath depends only on the entry it names.

## What the specifiers follow

Nothing is listed by hand. `composer admin:generate-shopware-modules` reads the sources that already
define the contract and writes two checked-in files:

- `shopware-modules.json`, which the Vite plugin and Jest read. The build never parses source.
- `src/shopware-virtual-modules.d.ts`, the ambient declarations.

The generator reads `Shopware.Utils` and `Shopware.Data` from the `export default { ... }` literals in
`src/core/service/util.service.ts` and `src/core/data/index.js`, and the two registries from the
`MixinContainer` and `PiniaRootState` interfaces in `src/global.types.ts`.

Both outputs are committed on purpose: a new specifier then shows up in a pull request diff, where a
reviewer can see whether it is real. `generate-shopware-modules.spec.ts` fails when either file drifts
from the sources and names the command to run.

Each module is declared with `export =`, which is what lets one specifier serve both halves:

```ts
import debug, { warn } from 'shopware:utils/debug';
```

## Constraints

**A mixin resolves on import.** A store subpath hands back a composable, so its store only has to be
registered by the time it is called. A mixin is a value, so importing `shopware:mixins/cms-element`
resolves that mixin there and then. Import a mixin from a component, which loads after boot. Module-level
mixins are registered by the `src/module/*/index.ts` files as they load, so anything running during that
has to keep using `Shopware.Mixin.getByName()`, or import the mixin's own file first the way
`sw-cms-element.mixin.ts` does for `cms-state`.

**Not usable before the global exists.** `src/index.ts` assigns `window.Shopware` before it imports
`src/app/main`, so application and extension code is always past that point. Code that runs earlier has
to read the global directly: anything `src/index.ts` imports statically, and the targets of an eager
`import.meta.glob` reached from there, such as `src/app/plugin/**`. Importing a `shopware:*` module too
early throws with that explanation instead of handing out `undefined`.

All of `src/core` stays on the global as a matter of layering, whether or not boot reaches a given file.
It is the Vue-independent framework code, and some of it is bundled into the admin worker, where there is
no `window.Shopware` at all.

**Extensions do not get their own registrations.** A store or mixin an extension registers itself is not
in the registry, so `shopware:stores/<its own id>` does not resolve. Use `Shopware.Store.get()` and
`Shopware.Mixin.getByName()`, or the composable the extension defines for itself.

## How it works

`build/vite-plugins/virtual-shopware-modules` resolves the specifiers and generates one ES module each,
with one `export const` per named export and a default export:

```js
const shopware = globalThis.Shopware;
if (!shopware) {
    throw new Error('"shopware:utils/debug" was imported before the global Shopware object existed. …');
}

export const warn = shopware.Utils["debug"]["warn"];
export const error = shopware.Utils["debug"]["error"];

export default shopware.Utils["debug"];
```

A mixin lookup carries `/*@__PURE__*/`, so a production build drops it when the importer's binding is
unused. The plugin runs in the Administration build and in extension builds, pointed at the host
Administration, so extensions resolve the same specifiers.

Jest does not run Vite. `test/_helper_/jest-resolver.js` writes a one-line stub per specifier on demand,
and the stub resolves the same value off the global object.
