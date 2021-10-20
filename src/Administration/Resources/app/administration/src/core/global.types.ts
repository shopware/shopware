import { ShopwareClass } from './shopware';

// trick to make it an "external module" to support global type extension
export {};

// declare global types
declare global {
    /**
     * "any" type which looks more awful in the code so that spot easier
     * the places where we need to fix the TS types
     */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    type $TSFixMe = any;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    type $TSFixMeFunction = (...args: any[]) => any;

    /**
     * Make the Shopware object globally available
     */
    const Shopware: ShopwareClass;
    interface Window { Shopware: ShopwareClass; }

    /**
     * Define global container for the bottle.js containers
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface ServiceContainer {}
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface InitContainer {}
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface FactoryContainer {}

    /**
     * Define global state for the Vuex store
     */
    // eslint-disable-next-line @typescript-eslint/no-empty-interface
    interface VuexRootState {}
}

/**
 * Link global bottle.js container to the bottle.js container interface
 */
declare module 'bottlejs' { // Use the same module name as the import string
    interface IContainer {
        factory: FactoryContainer,
        service: ServiceContainer,
        init: InitContainer,
    }
}
