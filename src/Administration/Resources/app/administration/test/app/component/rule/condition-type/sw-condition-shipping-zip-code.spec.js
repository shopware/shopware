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

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-condition-shipping-zip-code'), {
            stubs: {
                'sw-condition-operator-select': Shopware.Component.build('sw-condition-operator-select'),
                'sw-number-field': Shopware.Component.build('sw-number-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-tagged-field': Shopware.Component.build('sw-tagged-field'),
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-single-select': true,
                'sw-arrow-field': true,
                'sw-condition-type-select': true
            },
            provide: {
                conditionDataProviderService: new ConditionDataProviderService(),
                availableTypes: {},
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

    it('should get correct zipCodes', async () => {
        await wrapper.setProps({
            condition: {
                value: {
                    zipCodes: ['12345'],
                    operator: '>='
                }
            }
        });

        expect(wrapper.vm.zipCodes).toBe(12345);

        const input = wrapper.find('input[name=sw-field--zipCodes]');
        expect(input.element.value).toBe('12345');
    });
});
