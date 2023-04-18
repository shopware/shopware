import { createLocalVue, shallowMount } from '@vue/test-utils';
import swFlowDetailGeneral from 'src/module/sw-flow/view/detail/sw-flow-detail-general';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Component.register('sw-flow-detail-general', swFlowDetailGeneral);

async function createWrapper(privileges = [], query = {}) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-detail-general'), {
        localVue,
        provide: { repositoryFactory: {
            create: () => ({
                create: () => {
                    return Promise.resolve({});
                },
            }),
        },

        mocks: {
            $route: { params: {}, query: query },
        },

        acl: {
            can: (identifier) => {
                if (!identifier) {
                    return true;
                }

                return privileges.includes(identifier);
            },
        } },

        stubs: {
            'sw-number-field': true,
            'sw-card': true,
            'sw-text-field': true,
            'sw-textarea-field': true,
            'sw-container': true,
            'sw-switch-field': true,
        },
    });
}

describe('module/sw-flow/view/detail/sw-flow-detail-general', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', flowState);
    });

    it('should enabled element when have privilege', async () => {
        const wrapper = await createWrapper([
            'flow.editor',
        ]);

        const elementClasses = [
            '.sw-flow-detail-general__general-name',
            '.sw-flow-detail-general__general-description',
            '.sw-flow-detail-general__general-priority',
            '.sw-flow-detail-general__general-active',
        ];

        elementClasses.forEach(element => {
            const inputElement = wrapper.find(`${element}`);
            expect(inputElement.attributes().disabled).toBeFalsy();
        });
    });

    it('should disabled element when have not privilege', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);
        await flushPromises();
        const elementClasses = [
            '.sw-flow-detail-general__general-name',
            '.sw-flow-detail-general__general-description',
            '.sw-flow-detail-general__general-priority',
            '.sw-flow-detail-general__general-active',
        ];

        elementClasses.forEach(element => {
            const inputElement = wrapper.find(`${element}`);
            expect(inputElement.attributes().disabled).toBeTruthy();
        });
    });

    it('should not able to edit flow template', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);
        await flushPromises();
        await wrapper.setProps({
            isTemplate: true,
        });

        const alertElement = wrapper.findAll('.sw-flow-detail-general__template');
        expect(alertElement.exists()).toBe(true);
    });
});
