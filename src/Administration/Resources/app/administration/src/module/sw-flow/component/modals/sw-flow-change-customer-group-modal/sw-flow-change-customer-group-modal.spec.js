import { mount } from '@vue/test-utils';
import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 */

const customerGroupMock = [
    {
        translated: { name: 'Test net group' },
        id: '1',
    },
    {
        translated: { name: 'Test gross group' },
        id: '2',
    },
    {
        translated: { name: 'Test VIP group' },
        id: '3',
    },
];

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-flow-change-customer-group-modal', {
            sync: true,
        }),
        {
            propsData: {
                sequence: {},
            },
            global: {
                provide: {
                    shortcutService: {
                        startEventListener() {},
                        stopEventListener() {},
                    },
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(customerGroupMock),
                            };
                        },
                    },
                },
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'sw-icon': await wrapTestComponent('sw-icon'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                    'sw-loader': true,
                    'router-link': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-icon-deprecated': true,
                },
            },
        },
    );
}

describe('module/sw-flow/component/sw-flow-change-customer-group-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', flowState);
    });

    it('should show validation if customer group field is empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const customerGroupSelect = wrapper.find('.sw-entity-single-select');
        expect(customerGroupSelect.classes()).not.toContain('has--error');

        const saveButton = wrapper.find('.sw-flow-change-customer-group-modal__save-button');
        await saveButton.trigger('click');
        await flushPromises();

        expect(customerGroupSelect.classes()).toContain('has--error');

        await wrapper.find('.sw-select__select-indicator').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--1 .sw-select-result__result-item-text').trigger('click');
        await flushPromises();

        await saveButton.trigger('click');
        await flushPromises();

        expect(customerGroupSelect.classes()).not.toContain('has--error');
    });

    it('should emit process-finish when customer group is selected', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-select__select-indicator').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-result-list .sw-select-option--1').trigger('click');
        await flushPromises();

        const saveButton = wrapper.find('.sw-flow-change-customer-group-modal__save-button');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.emitted()['process-finish'][0]).toEqual([
            {
                config: {
                    customerGroupId: '2',
                },
            },
        ]);
    });

    it('should be able to close modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const cancelButton = wrapper.find('.sw-flow-change-customer-group-modal__cancel-button');
        expect(cancelButton.isVisible()).toBeTruthy();

        await cancelButton.trigger('click');
        await flushPromises();

        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });
});
