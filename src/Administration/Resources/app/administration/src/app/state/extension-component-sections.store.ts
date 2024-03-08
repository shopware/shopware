/**
 * @package admin
 */

import Vue from 'vue';
import type { Module } from 'vuex';
import type { uiComponentSectionRenderer } from '@shopware-ag/meteor-admin-sdk/es/ui/component-section';

type ComponentSectionEntry = Omit<uiComponentSectionRenderer, 'responseType' | 'positionId'>

interface ExtensionComponentSectionsState {
    identifier: {
        [positionId: string]: ComponentSectionEntry[]
    }
}

const ExtensionComponentSectionsStore: Module<ExtensionComponentSectionsState, VuexRootState> = {
    namespaced: true,

    state: (): ExtensionComponentSectionsState => ({
        identifier: {},
    }),

    mutations: {
        addSection(state, { component, positionId, src, props }: uiComponentSectionRenderer) {
            if (!state.identifier[positionId]) {
                Vue.set(state.identifier, positionId, []);
            }

            state.identifier[positionId].push({
                component,
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                src,
                props,
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
