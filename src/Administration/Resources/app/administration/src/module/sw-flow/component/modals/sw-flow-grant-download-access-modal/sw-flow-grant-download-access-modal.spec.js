import { shallowMount, createLocalVue } from '@vue/test-utils';
import swFlowGrantDownloadAccessModal from 'src/module/sw-flow/component/modals/sw-flow-grant-download-access-modal';
import 'src/app/component/form/select/base/sw-single-select';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Component.register('sw-flow-grant-download-access-modal', swFlowGrantDownloadAccessModal);

const { ShopwareError } = Shopware.Classes;

async function createWrapper(config = null) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-grant-download-access-modal'), {
        localVue,

        propsData: {
            sequence: config ? {
                config,
            } : {},
        },

        stubs: {
            'sw-modal': true,
            'sw-single-select': true,
        },
    });
}

describe('module/sw-flow/component/sw-flow-grant-download-access-modal', () => {
    Shopware.State.registerModule('swFlowState', {
        ...flowState,
        state: {
            invalidSequences: [],
            triggerEvent: {
                data: {
                    order: {
                        type: 'entity',
                    },
                },
                customerAware: false,
                extensions: [],
                logAware: false,
                mailAware: false,
                name: 'action.grant.download.access',
                orderAware: true,
                salesChannelAware: false,
                userAware: false,
                webhookAware: false,
            },
        },
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get config', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.getConfig()).toEqual({
            value: undefined,
        });
    });

    it('should return error if value is null', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.fieldError(wrapper.vm.value)).toBeInstanceOf(ShopwareError);
    });

    it('should emit on modal close', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.vm.onClose();

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should not emit on save with error', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.vm.onSave();

        expect(wrapper.emitted('process-finish')).toBeFalsy();
    });

    it('should emit on save', async () => {
        const wrapper = await createWrapper({
            value: true,
        });
        await wrapper.vm.$nextTick();

        await wrapper.vm.onSave();

        expect(wrapper.emitted('process-finish')).toBeTruthy();
        expect(wrapper.emitted('process-finish')[0][0]).toEqual({
            config: {
                value: true,
            },
        });
    });
});
