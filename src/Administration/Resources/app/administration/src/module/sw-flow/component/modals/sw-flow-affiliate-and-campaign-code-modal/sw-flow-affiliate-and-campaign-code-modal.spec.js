import { mount } from '@vue/test-utils';
import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 */

Shopware.Service().register('flowBuilderService', () => {
    return {
        mapActionType: () => {},

        getAvailableEntities: () => {
            return [
                {
                    label: 'Order',
                    value: 'order',
                },
                {
                    label: 'Customer',
                    value: 'customer',
                },
            ];
        },
    };
});

Shopware.State.registerModule('swFlowState', {
    ...flowState,
    state: {
        invalidSequences: [],
        triggerEvent: {
            data: {
                customer: {
                    type: 'entity',
                },
                order: {
                    type: 'entity',
                },
            },
            customerAware: true,
            extensions: [],
            logAware: false,
            mailAware: true,
            name: 'checkout.customer.login',
            orderAware: false,
            salesChannelAware: true,
            userAware: false,
            webhookAware: true,
        },
    },
});

const fieldClasses = [
    '.sw-flow-affiliate-and-campaign-code-modal__entity',
    '.sw-flow-affiliate-and-campaign-code-modal__affiliate-code',
    '.sw-flow-affiliate-and-campaign-code-modal__campaign-code',
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-flow-affiliate-and-campaign-code-modal', { sync: true }), {
        global: {
            provide: {
                validationService: {},
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(),
                        };
                    },
                },
            },
            stubs: {
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                            <slot name="modal-header"></slot>
                            <slot></slot>
                            <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'sw-button': {
                    template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                },
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-select-result': {
                    props: ['item', 'index'],
                    template: `
                        <li class="sw-select-result" @click.stop="onClickResult">
                            <slot></slot>
                        </li>
                    `,
                    methods: {
                        onClickResult() {
                            this.$parent.$parent.$emit('item-select', this.item);
                        },
                    },
                },
                'sw-loader': true,
                'sw-label': true,
                'sw-icon': true,
                'sw-field-error': true,
                'sw-highlight-text': true,
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
        props: {
            sequence: {},
        },
    });
}

describe('module/sw-flow/component/sw-flow-affiliate-and-campaign-code-modal', () => {
    it('should show these fields on modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should show error if do not select entity', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const removeEntity = wrapper.find('.sw-select__select-indicator-clear');
        await removeEntity.trigger('click');

        const buttonSave = wrapper.find('.sw-flow-affiliate-and-campaign-code-modal__save-button');
        await buttonSave.trigger('click');

        expect(wrapper.find('.sw-flow-affiliate-and-campaign-code-modal__entity').classes()).toContain('has--error');
    });

    it('should emit process-finish when affiliate and campaign code are entered', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const entitySelect = wrapper.find('.sw-single-select__selection');
        await entitySelect.trigger('click');
        await flushPromises();

        const entityInput = wrapper.find('.sw-select-result');
        await entityInput.trigger('click');

        const affiliateInput = wrapper.find('.sw-flow-affiliate-and-campaign-code-modal__affiliate-code #sw-field--affiliateCode-value');
        await affiliateInput.setValue('abc');
        await affiliateInput.trigger('input');

        const switchAffiliate = wrapper.find('.sw-flow-affiliate-and-campaign-code-modal__affiliate-code-overwrite input');
        await switchAffiliate.setChecked(true);

        const campaignInput = wrapper.find('.sw-flow-affiliate-and-campaign-code-modal__campaign-code #sw-field--campaignCode-value');
        await campaignInput.setValue('xyz');
        await campaignInput.trigger('input');

        const saveButton = wrapper.find('.sw-flow-affiliate-and-campaign-code-modal__save-button');
        await saveButton.trigger('click');

        expect(wrapper.emitted()['process-finish'][0]).toEqual([{
            config: {
                entity: 'order',
                affiliateCode: {
                    value: 'abc',
                    upsert: true,
                },
                campaignCode: {
                    value: 'xyz',
                    upsert: false,
                },
            },
        }]);
    });

    it('should show correctly the entity options', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.entityOptions).toHaveLength(2);
        wrapper.vm.entityOptions.forEach((option) => {
            expect(['Order', 'Customer']).toContain(option.label);
        });
    });
});
