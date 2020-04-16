import { shallowMount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-line-item-dimension-length';
import 'src/app/component/rule/sw-condition-operator-select';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

describe('components/rule/condition-type/sw-condition-line-item-dimension-length', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-condition-line-item-dimension-length'), {
            stubs: {
                'sw-condition-operator-select': Shopware.Component.build('sw-condition-operator-select'),
                'sw-number-field': Shopware.Component.build('sw-number-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
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
            },
            mocks: {
                $tc: key => key
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should allow input of float values', async () => {
        const input = wrapper.find('input[name=sw-field--amount]');
        input.setValue('3.56');
        input.trigger('change');
        expect(input.element.value).toBe('3.56');
    });
});
