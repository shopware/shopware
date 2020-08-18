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
            'sw-container': '<div class="sw-container"><slot></slot></div>',
            'sw-single-select': true,
            'sw-text-field': true,
            'sw-arrow-field': '<div class="sw-arrow-field"><slot></slot></div>',
            'sw-entity-single-select': true
        }
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-value', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should have disabled prop', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm.disabled).toBe(false);

        wrapper.setProps({ disabled: true });
        expect(wrapper.vm.disabled).toBe(true);
    });

    it.each([
        ['boolean', 'equals', 'sw-single-select-stub'],
        ['uuid', 'equals', 'sw-entity-single-select-stub', 'product'],
        ['uuid', 'equals', 'sw-entity-single-select-stub']
    ])('should have a disabled input with %s field type', (fieldType, actualCondition, element, entity = '') => {
        const wrapper = createWrapper(['product_stream.viewer'], fieldType, actualCondition, entity);
        wrapper.setProps({ disabled: true });

        const targetElement = wrapper.find(element);

        expect(targetElement.attributes('disabled')).toBe('true');
    });
});

