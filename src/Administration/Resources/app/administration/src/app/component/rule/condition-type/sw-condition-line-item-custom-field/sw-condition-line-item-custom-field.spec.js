import { shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-line-item-custom-field';
import 'src/app/component/rule/sw-condition-operator-select';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/rule/sw-condition-base-line-item';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/utils/sw-popover';

const mockCustomFields = new EntityCollection(
    '/custom-field',
    'custom_filed',
    null,
    {},
    [{
        id: '1',
        name: '',
        config: {
            label: 'foo',
        },
        customFieldSetId: '1',
        customFieldSet: {
            name: '',
            config: {
                label: 'bar',
            },
        },
        allowCartExpose: false,
    }, {
        id: '2',
        name: '',
        config: {
            label: 'bar',
        },
        customFieldSetId: '2',
        customFieldSet: {
            name: '',
            config: {
                label: 'baz',
            },
        },
        allowCartExpose: true,
    }],
    2,
    null,
);

describe('components/rule/condition-type/sw-condition-line-item-custom-field', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = shallowMount(await Shopware.Component.build('sw-condition-line-item-custom-field'), {
            stubs: {
                'sw-condition-operator-select': await Shopware.Component.build('sw-condition-operator-select'),
                'sw-number-field': await Shopware.Component.build('sw-number-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-select-base': await Shopware.Component.build('sw-select-base'),
                'sw-select-result': await Shopware.Component.build('sw-select-result'),
                'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
                'sw-entity-single-select': await Shopware.Component.build('sw-entity-single-select'),
                'sw-popover': await Shopware.Component.build('sw-popover'),
                'sw-single-select': true,
                'sw-form-field-renderer': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-condition-type-select': true,
                'sw-label': true,
                'sw-icon': true,
                'sw-loader': true,
            },
            provide: {
                conditionDataProviderService: new ConditionDataProviderService(),
                availableTypes: {},
                availableGroups: [],
                restrictedConditions: [],
                childAssociationField: {},
                validationService: {},
                insertNodeIntoTree: () => ({}),
                removeNodeFromTree: () => ({}),
                createCondition: () => ({}),
                conditionScopes: [],
                unwrapAllLineItemsCondition: () => ({}),
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(mockCustomFields),
                        };
                    },
                },
            },
            propsData: {
                condition: {
                    value: {
                        renderedField: '',
                    },
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render custom field options', async () => {
        expect(wrapper.vm).toBeTruthy();

        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        const listElements = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        expect(listElements.at(0).text()).toContain('foo');
        expect(listElements.at(0).text()).toContain('bar');
        expect(listElements.at(0).element).toHaveClass('is--disabled');

        expect(listElements.at(1).text()).toContain('bar');
        expect(listElements.at(1).text()).toContain('baz');
        expect(listElements.at(1).element).not.toHaveClass('is--disabled');
    });

    it('should get custom field option tooltip', async () => {
        expect(wrapper.vm).toBeTruthy();

        let tooltipConfig = wrapper.vm.getTooltipConfig(mockCustomFields.at(0));

        expect(tooltipConfig).toEqual({
            disabled: false,
            width: 260,
            message: 'global.sw-condition.condition.lineItemCustomField.field.customFieldSelect.tooltip',
        });

        tooltipConfig = wrapper.vm.getTooltipConfig(mockCustomFields.at(1));

        expect(tooltipConfig).toEqual({ message: '', disabled: true });
    });

    it('should set data on field change', async () => {
        expect(wrapper.vm).toBeTruthy();

        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        wrapper.vm.onFieldChange('1');

        expect(wrapper.vm.selectedFieldSet).toBe('1');
    });
});
