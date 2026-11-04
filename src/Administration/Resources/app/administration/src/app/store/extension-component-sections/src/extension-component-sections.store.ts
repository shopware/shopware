/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, reactive, type StoreOf, type uiComponentSectionRenderer } from '../dependencies';

/**
 * @public
 */
export type ComponentSectionEntry = Omit<uiComponentSectionRenderer, 'responseType' | 'positionId'> & {
    extensionName: string;
    priority?: number;
};

interface ExtensionComponentSectionsState {
    identifier: {
        [positionId: string]: ComponentSectionEntry[];
    };
}

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const extensionComponentSectionsStoreId = 'extensionComponentSections';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const extensionComponentSectionsStoreOptions = defineStoreOptions({
    id: extensionComponentSectionsStoreId,

    state: (): ExtensionComponentSectionsState => ({
        identifier: {},
    }),

    actions: {
        addSection({
            component,
            positionId,
            src,
            props,
            extensionName,
            priority,
        }: Omit<uiComponentSectionRenderer, 'responseType'> & { extensionName: string; priority?: number }) {
            if (!this.identifier[positionId]) {
                this.identifier[positionId] = reactive([]);
            }

            if (typeof priority === 'number' && priority < 1) {
                priority = undefined;
            }

            this.identifier[positionId].push({
                component,
                src,
                props,
                extensionName,
                priority,
            });
        },
    },
});

/**
 * @public
 */
export type ExtensionComponentSectionsStore = StoreOf<typeof extensionComponentSectionsStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useExtensionComponentSectionsStore = (): ExtensionComponentSectionsStore =>
    getStore(extensionComponentSectionsStoreId);
