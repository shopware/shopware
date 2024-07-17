/**
 * @package admin
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

import Vue from 'vue';
import type { Module } from 'vuex';
import type { uiComponentSectionRenderer } from '@shopware-ag/meteor-admin-sdk/es/ui/component-section';

type ComponentSectionEntry = Omit<uiComponentSectionRenderer, 'responseType' | 'positionId'> & { extensionName: string }

interface ExtensionComponentSectionsState {
    identifier: {
        [positionId: string]: ComponentSectionEntry[]
    },
}

const ExtensionComponentSectionsStore: Module<ExtensionComponentSectionsState, VuexRootState> = {
    namespaced: true,

    state: (): ExtensionComponentSectionsState => ({
        identifier: {},
    }),

    mutations: {
        addSection(
            state,
            { component, positionId, src, props, extensionName }: uiComponentSectionRenderer & { extensionName: string},
        ) {
            if (!state.identifier[positionId]) {
                Vue.set(state.identifier, positionId, []);
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
