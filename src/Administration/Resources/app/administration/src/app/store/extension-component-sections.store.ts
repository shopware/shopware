/**
 * @sw-package framework
 */
import type { uiComponentSectionRenderer } from '@shopware-ag/meteor-admin-sdk/es/ui/component-section';
import { reactive } from 'vue';

// eslint-disable-next-line max-len,sw-deprecation-rules/private-feature-declarations
export type ComponentSectionEntry = Omit<uiComponentSectionRenderer, 'responseType' | 'positionId'> & {
    extensionName: string;
};

interface ExtensionComponentSectionsState {
    identifier: {
        [positionId: string]: ComponentSectionEntry[];
    };
}

const ExtensionComponentSectionsStore = Shopware.Store.register({
    id: 'extensionComponentSections',

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
        }: Omit<uiComponentSectionRenderer, 'responseType'> & { extensionName: string }) {
            if (!this.identifier[positionId]) {
                this.identifier[positionId] = reactive([]);
            }

            this.identifier[positionId].push({
                component,
                src,
                props,
                extensionName,
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
