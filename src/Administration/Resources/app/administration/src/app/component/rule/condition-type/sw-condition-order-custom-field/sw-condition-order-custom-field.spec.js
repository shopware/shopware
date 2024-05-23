import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import EntityCollection from '../../../../../core/data/entity-collection.data';

const mockCustomFields = new EntityCollection(
    '/custom-field',
    'custom_field',
    null,
    {},
    [
        {
            id: '1',
            name: '',
            config: {
                type: 'text',
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
        },
        {
            id: '2',
            name: '',
            config: {
                componentName: 'sw-field',
                type: 'text',
                label: 'foo2',
            },
            customFieldSetId: '2',
            customFieldSet: {
                name: '',
                config: {
                    label: 'bar',
                },
            },
            allowCartExpose: true,
        },
    ],
    2,
    null,
);

async function createWrapper() {
    return mount(await wrapTestComponent('sw-condition-order-custom-field', { sync: true }), {
        global: {
            directives: {
                popover: Shopware.Directive.getByName('popover'),
            },
            stubs: {
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-condition-operator-select': await wrapTestComponent('sw-condition-operator-select'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
            },
            provide: {
                conditionDataProviderService: new ConditionDataProviderService(),
                availableTypes: [],
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
                    create: (param) => {
                        if (param === 'custom_field') {
                            return {
                                search: () => Promise.resolve(mockCustomFields),
                            };
                        }
                        return {
                            search: () => Promise.resolve(),
                            get: () => Promise.resolve(),
                        };
                    },
                },
            },
        },
        props: {
            condition: {
                value: {
                    renderedField: '',
                },
            },
        },
    });
}

describe('src/module/sw-flow/component/sw-flow-sequence', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should render custom field options', async () => {
        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        const listElements =
            document.body.querySelector('.sw-select-result-list__item-list').querySelectorAll('li');

        expect(listElements.item(0).querySelector('.sw-select-result__result-item-text').textContent)
            .toBe(' foo ');
        expect(listElements.item(0).querySelector('.sw-select-result__result-item-description').textContent)
            .toBe('bar');

        expect(listElements.item(1).querySelector('.sw-select-result__result-item-text').textContent)
            .toBe(' foo2 ');
        expect(listElements.item(1).querySelector('.sw-select-result__result-item-description').textContent)
            .toBe('bar');
    });

    it('should set data on field change with known id', async () => {
        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        wrapper.vm.onFieldChange('1');

        expect(wrapper.vm.renderedField).toStrictEqual(
            {
                allowCartExpose: false,
                config: {
                    label: 'foo',
                    type: 'text',
                },
                customFieldSet: {
                    config: {
                        label: 'bar',
                    },
                    name: '',
                },
                customFieldSetId: '1',
                id: '1',
                name: '',
            },
        );
        expect(wrapper.vm.selectedFieldSet).toBe('1');
    });

    it('should not set data on field change with unknown id', async () => {
        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        wrapper.vm.onFieldChange('3');

        expect(wrapper.vm.renderedField).toBeNull();
        expect(wrapper.vm.selectedFieldSet).toBeUndefined();
    });

    it('should set custom field value on input', async () => {
        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        document.body.querySelector('li:nth-of-type(2)').click();
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('foo2');
    });

    it('should set operator field value on input', async () => {
        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        document.body.querySelector('li:nth-of-type(2)').click();
        await flushPromises();

        await wrapper.find('.sw-single-select__selection-input').trigger('click');
        await flushPromises();

        document.body.querySelector('li').click();
        await flushPromises();

        expect(wrapper.find('.sw-single-select__selection-text').text())
            .toBe('global.sw-condition.operator.equals');
    });

    it('should set form field value on input', async () => {
        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        document.body.querySelector('li:nth-of-type(2)').click();
        await flushPromises();

        await wrapper.find('.sw-single-select__selection-input').trigger('click');
        await flushPromises();

        document.body.querySelector('li').click();
        await flushPromises();

        await wrapper.find('.sw-form-field-renderer input').setValue('test123');
        await flushPromises();

        expect(wrapper.vm.renderedFieldValue).toBe('test123');
    });
});
