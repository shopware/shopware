/**
 * @sw-package framework
 */
import type { uiComponentSectionRenderer } from '@shopware-ag/meteor-admin-sdk/es/ui/component-section';
import { reactive } from 'vue';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const extensionComponentSectionsStoreId = 'extensionComponentSections';

const ExtensionComponentSectionsStore = Shopware.Store.register({
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
 * @private
 */
export type ExtensionComponentSectionsStore = ReturnType<typeof ExtensionComponentSectionsStore>;

/**
 * @private
 */
export default ExtensionComponentSectionsStore;
