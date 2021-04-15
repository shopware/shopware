import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product-stream/component/sw-product-stream-filter';
import 'src/app/component/rule/sw-condition-base';

const EntityDefinitionFactory = require('src/core/factory/entity-definition.factory').default;

function createWrapper(privileges = []) {
    const mockEntitySchema = {
        product: {
            entity: 'product',
            properties: {}
        }
    };

    Shopware.EntityDefinition = EntityDefinitionFactory;
    Object.keys(mockEntitySchema).forEach((entity) => {
        Shopware.EntityDefinition.add(entity, mockEntitySchema[entity]);
    });

    return shallowMount(Shopware.Component.build('sw-product-stream-filter'), {
        stubs: {
            'sw-condition-type-select': true,
            'sw-text-field': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-field-error': true,
            'sw-product-stream-value': true,
            'sw-product-stream-field-select': true
        },
        provide: {
            conditionDataProviderService: {
                getPlaceholderData: () => {},
                getComponentByCondition: () => {}
            },
            availableTypes: {},
            childAssociationField: {},
            createCondition: () => {},
            productCustomFields: {
                test: 'customFields.test'
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            insertNodeIntoTree: () => {},
            removeNodeFromTree: () => {}
        },
        propsData: {
            condition: {}
        }
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-filter', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should return correct tooltip settings', () => {
        const wrapper = createWrapper();
        const tooltipObject = wrapper.vm.getNoPermissionsTooltip();

        expect(tooltipObject).toEqual({
            appearance: 'dark',
            disabled: true,
            message: 'sw-privileges.tooltip.warning',
            showDelay: 300,
            showOnDisabledElements: true
        });
    });

    it.each([
        ['true', 'sw-context-button-stub', 'product_stream.viewer'],
        [undefined, 'sw-context-button-stub', 'product_stream.viewer, product_stream.editor'],
        ['true', 'sw-product-stream-value-stub', 'product_stream.viewer'],
        [undefined, 'sw-product-stream-value-stub', 'product_stream.viewer, product_stream.editor'],
        ['true', 'sw-product-stream-field-select-stub', 'product_stream.viewer'],
        [undefined, 'sw-product-stream-field-select-stub', 'product_stream.viewer, product_stream.editor']
    ])('should have %p as disabled state on \'%s\' when having %s role', (state, element, role) => {
        const roles = role.split(', ');

        const wrapper = createWrapper(roles);
        const targetElement = wrapper.find(element);

        expect(targetElement.attributes('disabled')).toBe(state);
    });

    it('should return correct custom fields', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            condition: {
                field: 'customFields.test'
            }
        });
        wrapper.vm.$nextTick();

        expect(wrapper.vm.fields).toEqual(['customFields.test']);
    });

    it('should return true if input is custom field', async () => {
        const wrapper = createWrapper();
        wrapper.vm.$nextTick();

        expect(wrapper.vm.isCustomField('customFields.test')).toBe(true);
    });
});

