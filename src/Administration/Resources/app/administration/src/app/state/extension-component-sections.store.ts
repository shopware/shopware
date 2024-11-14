/**
 * @package admin
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

import Vue, { reactive } from 'vue';
// @ts-expect-error - compatUtils is not typed
import { compatUtils } from '@vue/compat';
import type { Module } from 'vuex';
import type { uiComponentSectionRenderer } from '@shopware-ag/meteor-admin-sdk/es/ui/component-section';

type ComponentSectionEntry = Omit<uiComponentSectionRenderer, 'responseType' | 'positionId'> & { extensionName: string };

interface ExtensionComponentSectionsState {
    identifier: {
        [positionId: string]: ComponentSectionEntry[];
    };
}

const ExtensionComponentSectionsStore: Module<ExtensionComponentSectionsState, VuexRootState> = {
    namespaced: true,

    state: (): ExtensionComponentSectionsState => ({
        identifier: {},
    }),

    mutations: {
        addSection(
            state,
            { component, positionId, src, props, extensionName }: uiComponentSectionRenderer & { extensionName: string },
        ) {
            if (!state.identifier[positionId]) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                if (compatUtils.isCompatEnabled('GLOBAL_SET')) {
                    Vue.set(state.identifier, positionId, []);
                } else {
                    state.identifier[positionId] = reactive([]);
                }
            }

            state.identifier[positionId].push({
                component,
                src,
                props,
                extensionName,
            });
        },
    },
};

/**
 * @private
 */
export default ExtensionComponentSectionsStore;
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { ExtensionComponentSectionsState, ComponentSectionEntry };
