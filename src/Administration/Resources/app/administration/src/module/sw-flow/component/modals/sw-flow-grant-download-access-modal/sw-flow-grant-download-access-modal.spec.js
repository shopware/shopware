import { mount } from '@vue/test-utils';
import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 */

const { ShopwareError } = Shopware.Classes;

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

async function createWrapper(config = null) {
    return mount(await wrapTestComponent('sw-flow-grant-download-access-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-single-select': true,
                'sw-button': {
                    emits: ['click'],
                    template: '<button @click="$emit(\'click\')"><slot></slot></button>',
                },
                'sw-icon': true,
                'sw-loader': true,
            },
            provide: {
                shortcutService: {
                    stopEventListener: jest.fn(),
                    startEventListener: jest.fn(),
                },
            },
        },
        props: {
            sequence: config ? {
                config,
            } : {},
        },
    });
}

describe('module/sw-flow/component/sw-flow-grant-download-access-modal', () => {
    it('should get config', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.getConfig()).toEqual({
            value: undefined,
        });
    });

    it('should return error if value is not a boolean', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.fieldError(wrapper.vm.value)).toBeInstanceOf(ShopwareError);
    });

    it('should emit on modal close', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onClose();

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should not emit on save with error', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const valueField = wrapper.find('.sw-flow-grant-download-access-modal__value-field');
        expect(valueField.attributes('error')).toBeUndefined();

        await wrapper.find('.sw-flow-grant-download-access-modal__save-button').trigger('click');

        expect(valueField.attributes('error')).toBeDefined();
        expect(wrapper.emitted('process-finish')).toBeFalsy();
    });

    it('should emit on save', async () => {
        const wrapper = await createWrapper({
            value: true,
        });
        await flushPromises();

        const valueField = wrapper.find('.sw-flow-grant-download-access-modal__value-field');
        expect(valueField.attributes('error')).toBeUndefined();

        await wrapper.find('.sw-flow-grant-download-access-modal__save-button')
            .trigger('click');
        await flushPromises();

        expect(valueField.attributes('error')).toBeUndefined();
        expect(wrapper.emitted('process-finish')).toBeTruthy();
        expect(wrapper.emitted('process-finish')[0][0]).toEqual({
            config: {
                value: true,
            },
        });
    });
});
