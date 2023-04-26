import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/condition-type/sw-condition-order-custom-field';
import 'src/app/component/rule/sw-condition-operator-select';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';

Shopware.Service().register('conditionDataProviderService', () => {
    return new ConditionDataProviderService();
});

async function createWrapper(propsData) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {
        unbind() {
            return {
                hasAttribute() {},
            };
        },
    });
    return shallowMount(await Shopware.Component.build('sw-condition-order-custom-field'), {
        stubs: {
            'sw-condition-base': await Shopware.Component.build('sw-condition-base'),
            'sw-condition-type-select': {
                template: '<div class="sw-condition-type-select"></div>',
            },
            'sw-entity-single-select': await Shopware.Component.build('sw-entity-single-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-icon': true,
            'sw-field-error': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-loader': true,
            'sw-highlight-text': true,
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
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
            'sw-condition-operator-select': await Shopware.Component.build('sw-condition-operator-select'),
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-form-field-renderer': await Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
        },

        propsData: {
            ...propsData,
        },

        provide: {
            validationService: {},

            repositoryFactory: {
                create: () => {
                    return {
                        get: () => {
                            return Promise.resolve({
                                config: {
                                    label: null,
                                    componentName: 'sw-field',
                                },
                            });
                        },

                        search: () => Promise.resolve([
                            {
                                id: 'field-set-id-1',
                                config: {
                                    label: {
                                        'en-GB': 'sit quo amet',
                                    },
                                    componentName: 'sw-field',
                                },
                            },
                        ]),
                    };
                },
            },
            conditionDataProviderService: Shopware.Service('conditionDataProviderService'),
            availableTypes: [],
            childAssociationField: {},
            availableGroups: [],
        },
    });
}

describe('src/module/sw-flow/component/sw-flow-sequence', () => {
    it('should be able to show the elements when trigger', async () => {
        const condition = {
            type: 'orderCustomField',
            value: {
                renderedField: {
                    config: {
                        componentName: 'sw-field',
                    },
                },
                selectedField: '2b9c448cfbdf4f55b256221c66cc73b9',
                selectedFieldSet: '9b8018573b644e1686e878bb4b7dc688',
                operator: null,
                renderedFieldValue: null,
            },
        };
        const wrapper = await createWrapper({ condition });
        await wrapper.vm.$nextTick();
        wrapper.vm.$refs = {
            selectedField: {
                resultCollection: {
                    has: () => {
                        return true;
                    },
                    get: () => {
                        return {
                            config: {
                                componentName: 'sw-field',
                            },
                        };
                    },
                },
            },
        };

        // Find and trigger on custom field
        const orderCustomField = await wrapper.find('.sw-condition-order-custom-field .sw-select__selection');
        expect(orderCustomField).toBeTruthy();
        await orderCustomField.trigger('click');
        const customFieldList = await wrapper.find('.sw-select-result-list__item-list');
        expect(customFieldList).toBeTruthy();
        await wrapper.vm.$nextTick();
        const customItem = await customFieldList.find('li.sw-select-result');
        await customItem.trigger('click');

        // Find and trigger on condition operator select
        const conditionOrderCustomField = await wrapper.find('.sw-condition-operator-select .sw-select__selection');
        expect(conditionOrderCustomField).toBeTruthy();
        await conditionOrderCustomField.trigger('click');

        const conditionOrderCustomFieldList = await wrapper.find('.sw-select-result-list__item-list');
        expect(conditionOrderCustomFieldList).toBeTruthy();
        const conditionOrderItem = await conditionOrderCustomFieldList.findAll('li.sw-select-result').at(0);
        expect(conditionOrderItem).toBeTruthy();
    });

    it('should on field change event is invalid', async () => {
        const condition = {
            type: 'orderCustomField',
            value: {
                renderedField: {
                    config: {
                        componentName: 'sw-field',
                    },
                },
                selectedField: '2b9c448cfbdf4f55b256221c66cc73b9',
                selectedFieldSet: '9b8018573b644e1686e878bb4b7dc688',
                operator: null,
                renderedFieldValue: null,
            },
        };
        const wrapper = await createWrapper({ condition });
        await wrapper.vm.$nextTick();
        wrapper.vm.$refs = {
            selectedField: {
                resultCollection: {
                    has: () => {
                        return false;
                    },
                    get: () => {
                        return {
                            config: {
                                componentName: 'sw-field',
                            },
                        };
                    },
                },
            },
        };

        // Find and trigger on custom field
        const orderCustomField = await wrapper.find('.sw-condition-order-custom-field .sw-select__selection');
        expect(orderCustomField).toBeTruthy();
        await orderCustomField.trigger('click');
        const customFieldList = await wrapper.find('.sw-select-result-list__item-list');
        expect(customFieldList).toBeTruthy();
        await wrapper.vm.$nextTick();
        const customItem = await customFieldList.find('li.sw-select-result');
        await customItem.trigger('click');
        expect(wrapper.vm.renderedField).toBeNull();
    });
});
