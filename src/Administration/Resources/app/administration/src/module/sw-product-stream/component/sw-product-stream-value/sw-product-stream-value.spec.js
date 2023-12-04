/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper(
    privileges = [],
    fieldType = null,
    conditionType = '',
    entity = '',
    render = false,
) {
    let stubs = {
        'sw-container': {
            template: '<div class="sw-container"><slot></slot></div>',
        },
        'sw-single-select': true,
        'sw-text-field': true,
        'sw-arrow-field': {
            template: '<div class="sw-arrow-field"><slot></slot></div>',
        },
        'sw-entity-single-select': true,
    };

    if (render) {
        stubs = {
            ...stubs,
            'sw-single-select': await wrapTestComponent('sw-single-select'),
            'sw-select-base': await wrapTestComponent('sw-select-base'),
            'sw-block-field': await wrapTestComponent('sw-block-field'),
            'sw-base-field': await wrapTestComponent('sw-base-field'),
            'sw-select-result': await wrapTestComponent('sw-select-result'),
            'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
            'sw-popover': true,
            'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
            'sw-field-error': await wrapTestComponent('sw-field-error'),
            'sw-icon': {
                template: '<div class="sw-icon" @click="$emit(\'click\')"></div>',
            },
        };
    }

    return mount(await wrapTestComponent('sw-product-stream-value', { sync: true }), {
        props: {
            definition: {
                type: fieldType,
                entity,
                getField: () => {
                    return fieldType === '' ? null : { type: fieldType };
                },
                isJsonField: () => false,
                filterProperties: () => {
                    return {};
                },
            },
            condition: {
                type: conditionType,
            },
        },
        global: {
            renderStubDefaultSlot: true,
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                        },
                    }),
                },
                conditionDataProviderService: {
                    getOperatorSet: () => [],
                    allowedJsonAccessors: {
                        'json.test': {
                            value: 'json.test',
                            type: 'string',
                        },
                    },
                },
                acl: {
                    can: identifier => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                productCustomFields: {
                    test: 'customFields.test',
                },
            },
            stubs,
        },
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-value', () => {
    it('should have disabled prop', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.disabled).toBe(false);

        await wrapper.setProps({ disabled: true });
        expect(wrapper.vm.disabled).toBe(true);
    });

    it.each([
        ['boolean', 'equals', 'sw-single-select-stub'],
        ['empty', 'equals', 'sw-single-select-stub'],
        ['uuid', 'equals', 'sw-entity-single-select-stub', 'product'],
        ['uuid', 'equals', 'sw-entity-single-select-stub'],
    ])('should have a disabled input with %s field type', async (fieldType, actualCondition, element, entity = '') => {
        const wrapper = await createWrapper(['product_stream.viewer'], fieldType, actualCondition, entity, false);
        await wrapper.setProps({ disabled: true });

        const targetElement = wrapper.get(element);

        expect(targetElement.attributes('disabled')).toBe('true');
    });

    it('should have a disabled input with json_list field type', async () => {
        const wrapper = await createWrapper(['product_stream.viewer'], 'json_list', 'equals', '', true);
        await wrapper.setProps({ disabled: true, fieldName: 'states' });

        const targetElement = wrapper.get('.sw-single-select');

        expect(targetElement.attributes('disabled')).toBe('true');
    });

    it('should render if is a json field', async () => {
        const wrapper = await createWrapper(['product_stream.viewer'], 'testingType', 'equals', '');
        await wrapper.setProps({
            disabled: true,
            definition: {
                type: 'testingType',
                entity: '',
                getField: () => ({ type: 'testingType' }),
                isJsonField: () => true,
                filterProperties: () => {
                    return {};
                },
            },
        });
        const targetElement = wrapper.get('sw-single-select-stub');

        expect(targetElement.attributes('disabled')).toBe('true');
    });

    it('should render placeholder if no definition exists', async () => {
        const wrapper = await createWrapper(['product_stream.viewer'], '', 'equals', '');
        await wrapper.setProps({
            disabled: true,
            fieldType: 'uuid',
        });

        expect(wrapper.get('.sw-product-stream-value__placeholder').exists()).toBe(true);
    });

    it('should return correct fieldDefinition', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            fieldName: 'customFields.test',
            definition: {
                entity: 'product',
                getField: () => undefined,
                isJsonField: () => false,
            },
        });
        await flushPromises();

        expect(wrapper.vm.fieldDefinition).toBe('customFields.test');
    });

    it('should fire event when trigger value for boolean type', async () => {
        const wrapper = await createWrapper(['product_stream.viewer'], 'boolean', 'equals', '', true);
        await flushPromises();

        const productStreamValueSwitch = wrapper.get('.sw-product-stream-value');
        await productStreamValueSwitch.get('.sw-select__selection').trigger('click');
        await flushPromises();

        await productStreamValueSwitch.get('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('boolean-change')).toBeTruthy();
    });

    it('should fire event with type \`equals\` when trigger value for boolean type YES', async () => {
        const wrapper = await createWrapper(['product_stream.viewer'], 'boolean', 'equals', '', true);
        await flushPromises();

        const productStreamValueSwitch = wrapper.get('.sw-product-stream-value');
        await productStreamValueSwitch.get('.sw-select__selection').trigger('click');
        await flushPromises();

        const productStreamValueYes = productStreamValueSwitch.findAll('.sw-select-result').at(0);

        expect(productStreamValueYes.text()).toBe('global.default.yes');
        await productStreamValueYes.trigger('click');
        await flushPromises();

        expect(wrapper.emitted('boolean-change')).toBeTruthy();
        expect(wrapper.emitted('boolean-change')[0][0].type).toBe('equals');
        expect(wrapper.emitted('boolean-change')[0][0].value).toBe('1');
    });

    it('should fire event with type \`not\` when trigger value for boolean type No', async () => {
        const wrapper = await createWrapper(['product_stream.viewer'], 'boolean', 'equals', '', true);
        await flushPromises();

        const productStreamValueSwitch = wrapper.get('.sw-product-stream-value');
        await productStreamValueSwitch.get('.sw-select__selection').trigger('click');
        await flushPromises();

        const productStreamValueNo = productStreamValueSwitch.findAll('.sw-select-result').at(1);

        expect(productStreamValueNo.text()).toBe('global.default.no');
        await productStreamValueNo.trigger('click');

        expect(wrapper.emitted('boolean-change')).toBeTruthy();
        expect(wrapper.emitted('boolean-change')[0][0].type).toBe('notEquals');
        expect(wrapper.emitted('boolean-change')[0][0].value).toBe('0');
    });

    it('should fire events with correct types when trigger value for empty type changes', async () => {
        const wrapper = await createWrapper(['product_stream.viewer'], 'empty', 'equals', '', true);
        await flushPromises();

        const productStreamValueSwitch = wrapper.get('.sw-product-stream-value');
        await productStreamValueSwitch.get('.sw-select__selection').trigger('click');
        await flushPromises();

        let productStreamValueYes = productStreamValueSwitch.findAll('.sw-select-result').at(0);

        expect(productStreamValueYes.text()).toBe('global.default.yes');
        await productStreamValueYes.trigger('click');
        await flushPromises();

        expect(wrapper.emitted('empty-change')).toBeTruthy();
        expect(wrapper.emitted('empty-change')[0][0].type).toBe('notEquals');

        await productStreamValueSwitch.get('.sw-select__selection').trigger('click');
        await flushPromises();

        productStreamValueYes = productStreamValueSwitch.findAll('.sw-select-result').at(1);

        expect(productStreamValueYes.text()).toBe('global.default.no');
        await productStreamValueYes.trigger('click');
        await flushPromises();

        expect(wrapper.emitted('empty-change')[1][0].type).toBe('equals');
    });

    it('should return correct fieldDefinition with json accessor', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            fieldName: 'json.test',
            definition: {
                entity: 'product',
                getField: () => undefined,
                isJsonField: () => false,
            },
        });
        await flushPromises();

        expect(wrapper.vm.fieldDefinition).toEqual({
            value: 'json.test',
            type: 'string',
        });
    });

    it('should return empty filterType for foreign key field of manyToOne relation', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            fieldName: 'fkField',
            definition: {
                entity: 'product',
                getField: () => {
                    return {
                        type: 'uuid',
                    };
                },
                isJsonField: () => false,
                filterProperties: (filter) => {
                    if (typeof filter !== 'function') {
                        return {};
                    }

                    const properties = {
                        fkField: {
                            localField: 'fkField',
                            relation: 'many_to_one',
                        },
                    };

                    const result = {};
                    Object.keys(properties).forEach((propertyName) => {
                        if (filter(properties[propertyName]) === true) {
                            result[propertyName] = properties[propertyName];
                        }
                    });

                    return result;
                },
            },
        });
        await flushPromises();

        expect(wrapper.vm.fieldType).toBe('empty');
    });
});

