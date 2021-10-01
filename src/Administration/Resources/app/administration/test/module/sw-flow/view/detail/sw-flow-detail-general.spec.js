import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-flow/view/detail/sw-flow-detail-general';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-detail-general'), {
        localVue,
        provide: { repositoryFactory: {
            create: () => ({
                create: () => {
                    return Promise.resolve({});
                }
            })
        },

        acl: {
            can: (identifier) => {
                if (!identifier) {
                    return true;
                }

                return privileges.includes(identifier);
            }
        } },

        stubs: {
            'sw-number-field': true,
            'sw-card': true,
            'sw-text-field': true,
            'sw-textarea-field': true,
            'sw-container': true,
            'sw-switch-field': true
        }
    });
}

enableAutoDestroy(afterEach);

describe('module/sw-flow/view/detail/sw-flow-detail-general', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', flowState);
    });

    it('should enabled element when have privilege', async () => {
        const wrapper = createWrapper([
            'flow.editor'
        ]);

        const elementClasses = [
            '.sw-flow-detail-general__general-name',
            '.sw-flow-detail-general__general-description',
            '.sw-flow-detail-general__general-priority',
            '.sw-flow-detail-general__general-active'
        ];

        elementClasses.forEach(element => {
            const inputElement = wrapper.find(`${element}`);
            expect(inputElement.attributes().disabled).toBeFalsy();
        });
    });

    it('should disabled element when have not privilege', async () => {
        const wrapper = createWrapper([
            'flow.viewer'
        ]);
        await wrapper.vm.$nextTick();
        const elementClasses = [
            '.sw-flow-detail-general__general-name',
            '.sw-flow-detail-general__general-description',
            '.sw-flow-detail-general__general-priority',
            '.sw-flow-detail-general__general-active'
        ];

        elementClasses.forEach(element => {
            const inputElement = wrapper.find(`${element}`);
            expect(inputElement.attributes().disabled).toBeTruthy();
        });
    });
});
