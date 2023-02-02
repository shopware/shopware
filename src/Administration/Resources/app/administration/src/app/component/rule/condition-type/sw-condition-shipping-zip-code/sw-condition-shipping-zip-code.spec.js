import { shallowMount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-shipping-zip-code';
import 'src/app/component/rule/sw-condition-operator-select';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-tagged-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

describe('components/rule/condition-type/sw-condition-shipping-zip-code', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = shallowMount(await Shopware.Component.build('sw-condition-shipping-zip-code'), {
            stubs: {
                'sw-condition-operator-select': await Shopware.Component.build('sw-condition-operator-select'),
                'sw-number-field': await Shopware.Component.build('sw-number-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-tagged-field': await Shopware.Component.build('sw-tagged-field'),
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-single-select': true,
                'sw-arrow-field': true,
                'sw-condition-type-select': true,
                'sw-label': true
            },
            provide: {
                conditionDataProviderService: new ConditionDataProviderService(),
                availableTypes: {},
                availableGroups: [],
                restrictedConditions: [],
                childAssociationField: {},
                validationService: {}
            },
            propsData: {
                condition: {}
            }
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get correct numeric zipCodes', async () => {
        await wrapper.setProps({
            condition: {
                value: {
                    zipCodes: ['12345'],
                    operator: '>='
                }
            }
        });
        await wrapper.setData({
            isNumeric: true
        });

        expect(wrapper.vm.zipCodes).toBe(12345);

        const input = wrapper.find('input[name=sw-field--zipCodes]');
        expect(input.element.value).toBe('12345');
    });

    it('should get correct alphanumeric zipCodes', async () => {
        await wrapper.setProps({
            condition: {
                value: {
                    zipCodes: ['12345'],
                    operator: '='
                }
            }
        });
        await wrapper.setData({
            isNumeric: false
        });

        expect(wrapper.vm.zipCodes).toStrictEqual(['12345']);

        const tagList = wrapper.find('.sw-tagged-field__tag-list');
        expect(tagList.text()).toContain('12345');
    });
});
