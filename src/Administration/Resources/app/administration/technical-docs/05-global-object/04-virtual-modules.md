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
overrides, the plugin boot order, and every existing `Shopware.*` call keep working unchanged. Nothing has
to be migrated, and both styles can appear in the same file.

## Available modules

| Specifier | Exposes | Export per entry |
| --- | --- | --- |
| `shopware:utils` | `Shopware.Utils` | its own name, e.g. `createId`, `debounce`, `object`, `format` |
| `shopware:data` | `Shopware.Data` | its own name, e.g. `Criteria`, `EntityCollection`, `Repository` |
| `shopware:mixins` | the mixins on `Shopware.Mixin` | camelCase plus `Mixin`, e.g. `sw-form-field` becomes `swFormFieldMixin` |
| `shopware:stores` | the Pinia stores on `Shopware.Store` | `use<Id>Store`, e.g. `swOrderDetail` becomes `useSwOrderDetailStore` |

```ts
import { Criteria } from 'shopware:data';
import { swFormFieldMixin, removeApiErrorMixin } from 'shopware:mixins';
import { useSwOrderDetailStore } from 'shopware:stores';

export default {
    mixins: [
        swFormFieldMixin,
        removeApiErrorMixin,
    ],

    created() {
        const orderDetailStore = useSwOrderDetailStore();
        const criteria = new Criteria(1, 25);
    },
};
```

Use named imports. There is no default export, because a namespace object would defeat tree shaking and
hide typos behind `undefined`.

## What the export names follow

Nothing is listed by hand. The export names are read out of the sources that already define the contract,
so a new utility, DAL class, mixin, or store is importable without touching the build:

- `Shopware.Utils` and `Shopware.Data` are `export default { ... }` object literals, and their keys are
  the export names.
- `Shopware.Mixin` and `Shopware.Store` are runtime registries whose declared contract is the
  `MixinContainer` and `PiniaRootState` interfaces in `src/global.types.ts`. What is declared there is
  what can be imported.

The types in `src/shopware-virtual-modules.d.ts` map over the same two interfaces, which is why they never
need an update either.

## Constraints

**`shopware:mixins` is not usable during boot.** `useSwOrderDetailStore()` looks its store up when
called, so a store only has to be registered by the time it is used. A mixin is a value, so importing
`shopware:mixins` resolves every mixin the module publishes. A production build tree-shakes the ones
nobody imported; the development server serves the module unbundled and resolves all of them.

Module-level mixins are registered by the `src/module/*/index.ts` files as they load, so any importer
that runs while that is still in progress sees a mixin that is not there yet. That is reachable today,
from a mixin file that imports the module:

```
Error: The mixin "cart-notification" is not registered.
    at Object.getByName (src/core/factory/mixin.factory.ts:35:11)
    at @id/__x00__shopware:mixins:19:67
```

Import a mixin from a component, which loads after boot. `Shopware.Mixin.getByName()` stays the way to
reach one from anything that runs earlier.

**Not usable before the global exists.** `src/index.ts` assigns `window.Shopware` before it imports
`src/app/main`, so application and extension code is always past that point. Code that runs earlier -
`src/core/**` and anything `src/index.ts` imports statically - has to read the global directly. Importing
a `shopware:*` module too early throws with that explanation instead of handing out `undefined`.

**Extensions do not get their own registrations.** A store or mixin an extension registers itself is not
exported by `shopware:stores` or `shopware:mixins`; use `Shopware.Store.get()` and
`Shopware.Mixin.getByName()`, or the `useXStore` composable the extension defines for itself.

## How it works

`build/vite-plugins/virtual-shopware-modules` resolves the specifiers and generates one ES module per
specifier, containing one `export const` per entry:

```js
const shopware = globalThis.Shopware;
if (!shopware) {
    throw new Error('"shopware:data" was imported before the global Shopware object existed. …');
}

export const Criteria = shopware.Data["Criteria"];
```

The plugin runs in the Administration build and in extension builds, pointed at the host Administration,
so extensions resolve the same specifiers. Jest does not run Vite, so `moduleNameMapper` points the
specifiers at `test/_helper_/virtual-shopware-modules`, which resolves the same exports off the global
object at property-access time.
