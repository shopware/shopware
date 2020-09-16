import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product-stream/component/sw-product-stream-value';
import 'src/app/component/rule/sw-condition-base';

function createWrapper(privileges = [], fieldType = null, conditionType = '', entity = '') {
    return shallowMount(Shopware.Component.build('sw-product-stream-value'), {
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {}
                })
            },
            conditionDataProviderService: {
                getOperatorSet: () => []
            },
            acl: {
                can: identifier => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            productCustomFields: []
        },
        propsData: {
            definition: {
                type: fieldType,
                entity,
                getField: () => ({ type: fieldType }),
                isJsonField: () => false
            },
            condition: {
                type: conditionType
            }
        },
        mocks: {
            $tc: key => key
        },
        stubs: {
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-single-select': true,
            'sw-text-field': true,
            'sw-arrow-field': {
                template: '<div class="sw-arrow-field"><slot></slot></div>'
            },
            'sw-entity-single-select': true
        }
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-value', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled prop', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.disabled).toBe(false);

        await wrapper.setProps({ disabled: true });
        expect(wrapper.vm.disabled).toBe(true);
    });

    it.each([
        ['boolean', 'equals', 'sw-single-select-stub'],
        ['uuid', 'equals', 'sw-entity-single-select-stub', 'product'],
        ['uuid', 'equals', 'sw-entity-single-select-stub']
    ])('should have a disabled input with %s field type', async (fieldType, actualCondition, element, entity = '') => {
        const wrapper = await createWrapper(['product_stream.viewer'], fieldType, actualCondition, entity);
        await wrapper.setProps({ disabled: true });

        const targetElement = wrapper.find(element);

        expect(targetElement.attributes('disabled')).toBe('true');
    });
});

