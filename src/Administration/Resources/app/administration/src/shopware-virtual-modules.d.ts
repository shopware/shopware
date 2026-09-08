/**
 * @sw-package framework
 *
 * Types for the `shopware:*` modules, which expose branches of the global `Shopware` object as ordinary
 * named imports. `build/vite-plugins/virtual-shopware-modules` generates their runtime counterpart.
 *
 * Every module is typed as the object the global already holds, so these declarations follow the global
 * object by themselves - a new store in `PiniaRootState` or a new mixin in `MixinContainer` is importable
 * from here without touching this file.
 */

/**
 * The utility functions of `Shopware.Utils`.
 *
 * ```ts
 * import { createId, debounce } from 'shopware:utils';
 * ```
 */
declare module 'shopware:utils' {
    import type utilities from 'src/core/service/util.service';

    const utils: typeof utilities;

    export = utils;
}

/**
 * The data-abstraction-layer classes of `Shopware.Data`.
 *
 * ```ts
 * import { Criteria } from 'shopware:data';
 *
 * const criteria = new Criteria(1, 25);
 * ```
 */
declare module 'shopware:data' {
    import type dataClasses from 'src/core/data/index';

    const data: typeof dataClasses;

    export = data;
}

/**
 * The mixins registered on `Shopware.Mixin`, one export per `MixinContainer` entry.
 *
 * ```ts
 * import { swFormFieldMixin, removeApiErrorMixin } from 'shopware:mixins';
 *
 * mixins: [swFormFieldMixin, removeApiErrorMixin],
 * ```
 */
declare module 'shopware:mixins' {
    /**
     * Turns a kebab-cased registry name into its camelCase export name, e.g. `sw-form-field` into
     * `swFormField`. Names that are already camelCase pass through unchanged.
     */
    type CamelCase<Name extends string> = Name extends `${infer Head}-${infer Rest}`
        ? `${Head}${Capitalize<CamelCase<Rest>>}`
        : Name;

    const mixins: {
        [Name in keyof MixinContainer as `${CamelCase<Name & string>}Mixin`]: MixinContainer[Name];
    };

    export = mixins;
}

/**
 * The Pinia stores registered on `Shopware.Store`, one composable per `PiniaRootState` entry.
 *
 * Each export resolves its store when called, not when the module is imported, so a store only has to be
 * registered by the time it is used.
 *
 * ```ts
 * import { useSwOrderDetailStore } from 'shopware:stores';
 *
 * const orderDetailStore = useSwOrderDetailStore();
 * ```
 */
declare module 'shopware:stores' {
    const stores: {
        [Id in keyof PiniaRootState as `use${Capitalize<Id & string>}Store`]: () => PiniaRootState[Id];
    };

    export = stores;
}
