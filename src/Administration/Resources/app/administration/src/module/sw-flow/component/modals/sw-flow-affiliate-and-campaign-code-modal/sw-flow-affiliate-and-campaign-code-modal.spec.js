import { shallowMount, createLocalVue } from '@vue/test-utils';
import swFlowAffiliateAndCampaignCodeModal from 'src/module/sw-flow/component/modals/sw-flow-affiliate-and-campaign-code-modal';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/entity/sw-entity-tag-select';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/base/sw-container';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Component.register('sw-flow-affiliate-and-campaign-code-modal', swFlowAffiliateAndCampaignCodeModal);

const fieldClasses = [
    '.sw-flow-affiliate-and-campaign-code-modal__entity',
    '.sw-flow-affiliate-and-campaign-code-modal__affiliate-code',
    '.sw-flow-affiliate-and-campaign-code-modal__campaign-code',
];

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-affiliate-and-campaign-code-modal'), {
        localVue,
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

        propsData: {
            sequence: {},
        },

        stubs: {
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
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
                template: `<li class="sw-select-result" @click.stop="onClickResult">
                                <slot></slot>
                           </li>`,
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
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-container': await Shopware.Component.build('sw-container'),
        },
    });
}

describe('module/sw-flow/component/sw-flow-affiliate-and-campaign-code-modal', () => {
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

    it('should show these fields on modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should show error if do not select entity', async () => {
        const wrapper = await createWrapper();
        const removeEntity = wrapper.find('.sw-select__select-indicator-clear');
        await removeEntity.trigger('click');
        const buttonSave = wrapper.find('.sw-flow-affiliate-and-campaign-code-modal__save-button');
        await buttonSave.trigger('click');

        expect(wrapper.find('.sw-flow-affiliate-and-campaign-code-modal__entity').classes()).toContain('has--error');
    });

    it('should emit process-finish when affiliate and campaign code are entered', async () => {
        const wrapper = await createWrapper();

        const entitySelect = wrapper.find('.sw-single-select__selection');
        await entitySelect.trigger('click');

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
