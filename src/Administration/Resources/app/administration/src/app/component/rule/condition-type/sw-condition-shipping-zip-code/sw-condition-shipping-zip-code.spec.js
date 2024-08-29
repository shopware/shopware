/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';

describe('components/rule/condition-type/sw-condition-shipping-zip-code', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-condition-shipping-zip-code', { sync: true }), {
            props: {
                condition: {},
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-condition-operator-select': await wrapTestComponent('sw-condition-operator-select'),
                    'sw-number-field': await wrapTestComponent('sw-number-field'),
                    'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-tagged-field': await wrapTestComponent('sw-tagged-field'),
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-field-error': true,
                    'sw-single-select': true,
                    'sw-arrow-field': true,
                    'sw-condition-type-select': true,
                    'sw-label': true,
                    'sw-field-copyable': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
                provide: {
                    conditionDataProviderService: new ConditionDataProviderService(),
                    availableTypes: {},
                    availableGroups: [],
                    restrictedConditions: [],
                    childAssociationField: {},
                    validationService: {},
                },
            },
        });
    });

    it('should get correct numeric zipCodes', async () => {
        await wrapper.setProps({
            condition: {
                value: {
                    zipCodes: ['12345'],
                    operator: '>=',
                },
            },
        });
        await wrapper.setData({
            isNumeric: true,
        });
        await flushPromises();

        const swNumberFields = wrapper.findAll('.sw-field.sw-field--number');

        expect(swNumberFields).toHaveLength(1);

        const input = swNumberFields[0].get('input');
        expect(input.element.value).toBe('12345');
    });

    it('should get correct alphanumeric zipCodes', async () => {
        await wrapper.setProps({
            condition: {
                value: {
                    zipCodes: ['12345'],
                    operator: '=',
                },
            },
        });
        await wrapper.setData({
            isNumeric: false,
        });
        await flushPromises();

        const tagList = wrapper.find('.sw-tagged-field__tag-list');
        expect(tagList.text()).toContain('12345');
    });
});
