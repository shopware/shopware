/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';

async function createWrapper(privileges = []) {
    const mockEntitySchema = {
        product: {
            entity: 'product',
            properties: {},
        },
    };

    Shopware.EntityDefinition = EntityDefinitionFactory;
    Object.keys(mockEntitySchema).forEach((entity) => {
        Shopware.EntityDefinition.add(entity, mockEntitySchema[entity]);
    });

    return mount(await wrapTestComponent('sw-product-stream-filter', { sync: true }), {
        props: {
            condition: {},
        },
        global: {
            stubs: {
                'sw-condition-type-select': true,
                'sw-text-field': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-product-stream-value': true,
                'sw-product-stream-field-select': true,
            },
            provide: {
                conditionDataProviderService: {
                    getPlaceholderData: () => {},
                    getComponentByCondition: () => {},
                    getOperatorSet: () => [{ identifier: 'empty' }],
                    allowedJsonAccessors: {
                        'json.test': {
                            value: 'json.test',
                            type: 'string',
                        },
                        'cheapestPrice.percentage': {
                            value: 'cheapestPrice.percentage',
                            type: 'float',
                            trans: 'percentage',
                        },
                    },
                    isNegatedType: () => false,
                },
                availableTypes: {},
                availableGroups: [],
                childAssociationField: {},
                createCondition: () => {},
                productCustomFields: {
                    test: 'customFields.test',
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                insertNodeIntoTree: () => {},
                removeNodeFromTree: () => {},
            },
        },
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-filter', () => {
    it('should return correct tooltip settings', async () => {
        const wrapper = await createWrapper();
        const tooltipObject = wrapper.vm.getNoPermissionsTooltip();

        expect(tooltipObject).toEqual({
            appearance: 'dark',
            disabled: true,
            message: 'sw-privileges.tooltip.warning',
            showDelay: 300,
            showOnDisabledElements: true,
        });
    });

    it.each([
        [
            'true',
            'sw-context-button-stub',
            'product_stream.viewer',
        ],
        [
            undefined,
            'sw-context-button-stub',
            'product_stream.viewer, product_stream.editor',
        ],
        [
            'true',
            'sw-product-stream-value-stub',
            'product_stream.viewer',
        ],
        [
            undefined,
            'sw-product-stream-value-stub',
            'product_stream.viewer, product_stream.editor',
        ],
        [
            'true',
            'sw-product-stream-field-select-stub',
            'product_stream.viewer',
        ],
        [
            undefined,
            'sw-product-stream-field-select-stub',
            'product_stream.viewer, product_stream.editor',
        ],
    ])("should have %p as disabled state on '%s' when having %s role", async (state, element, role) => {
        const roles = role.split(', ');

        const wrapper = await createWrapper(roles);
        const targetElement = wrapper.find(element);

        expect(targetElement.attributes('disabled')).toBe(state);
    });

    it('should return correct custom fields', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            condition: {
                field: 'customFields.test',
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.fields).toEqual(['customFields.test']);
    });

    it('should return true if input is custom field', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isCustomField('customFields.test')).toBe(true);
    });

    it('should return correct json field', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            condition: {
                field: 'json.test',
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.fields).toEqual(['json.test']);
    });

    it('should set default value to 100 when selecting cheapestPrice.percentage field', async () => {
        const wrapper = await createWrapper(['product_stream.editor']);

        await wrapper.setProps({
            condition: {
                field: null,
                type: null,
                value: null,
                parameters: null,
            },
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.updateFields({ field: 'cheapestPrice.percentage', index: 0 });

        expect(wrapper.vm.actualCondition.value).toBe('100');
    });

    it('should set default value to 100 for cheapestPrice.percentage with non-range type', async () => {
        const wrapper = await createWrapper(['product_stream.editor']);

        await wrapper.setProps({
            condition: {
                field: 'cheapestPrice.percentage',
                type: null,
                value: null,
                parameters: null,
            },
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.changeType({ type: 'equals', parameters: null });

        expect(wrapper.vm.actualCondition.value).toBe('100');
    });

    it('should set default parameters to 100 for cheapestPrice.percentage with range type', async () => {
        const wrapper = await createWrapper(['product_stream.editor']);

        await wrapper.setProps({
            condition: {
                field: 'cheapestPrice.percentage',
                type: null,
                value: null,
                parameters: null,
            },
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.changeType({ type: 'range', parameters: { gte: null, lte: null } });

        expect(wrapper.vm.actualCondition.parameters).toEqual({ gte: 100, lte: 100 });
    });

    it('should not set default value for non-percentage fields', async () => {
        const wrapper = await createWrapper(['product_stream.editor']);

        await wrapper.setProps({
            condition: {
                field: null,
                type: null,
                value: null,
                parameters: null,
            },
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.updateFields({ field: 'cheapestPrice', index: 0 });

        expect(wrapper.vm.actualCondition.value).toBeNull();
    });
});
