import Vue from 'vue';
import type { Module } from 'vuex';
import type { uiComponentSectionRenderer } from '@shopware-ag/admin-extension-sdk/es/ui/componentSection';

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

export default ExtensionComponentSectionsStore;
export type { ExtensionComponentSectionsState, ComponentSectionEntry };
