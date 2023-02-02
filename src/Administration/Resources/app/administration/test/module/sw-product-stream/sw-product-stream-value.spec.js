import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product-stream/component/sw-product-stream-value';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-icon';


function createWrapper(privileges = [], fieldType = null, conditionType = '', entity = '', render = false) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('popover', {});

    let stubs = {
        'sw-container': {
            template: '<div class="sw-container"><slot></slot></div>'
        },
        'sw-single-select': true,
        'sw-text-field': true,
        'sw-arrow-field': {
            template: '<div class="sw-arrow-field"><slot></slot></div>'
        },
        'sw-entity-single-select': true
    };

    if (render) {
        stubs = {
            ...stubs,
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': {
                template: '<div class="sw-icon" @click="$emit(\'click\')"></div>'
            }
        };
    }

    return shallowMount(Shopware.Component.build('sw-product-stream-value'), {
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {}
                })
            },
            conditionDataProviderService: {
                getOperatorSet: () => [],
                allowedJsonAccessors: {
                    'json.test': {
                        value: 'json.test',
                        type: 'string'
                    }
                }
            },
            acl: {
                can: identifier => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            productCustomFields: {
                test: 'customFields.test'
            }
        },
        propsData: {
            definition: {
                type: fieldType,
                entity,
                getField: () => ({ type: fieldType }),
                isJsonField: () => false,
                filterProperties: () => {
                    return {};
                }
            },
            condition: {
                type: conditionType
            }
        },
        stubs: stubs
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
        ['empty', 'equals', 'sw-single-select-stub'],
        ['uuid', 'equals', 'sw-entity-single-select-stub', 'product'],
        ['uuid', 'equals', 'sw-entity-single-select-stub']
    ])('should have a disabled input with %s field type', async (fieldType, actualCondition, element, entity = '') => {
        const wrapper = await createWrapper(['product_stream.viewer'], fieldType, actualCondition, entity);
        await wrapper.setProps({ disabled: true });

        const targetElement = wrapper.find(element);

        expect(targetElement.attributes('disabled')).toBe('true');
    });

    it('should return correct fieldDefinition', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            fieldName: 'customFields.test',
            definition: {
                entity: 'product',
                getField: () => undefined,
                isJsonField: () => false
            }
        });
        wrapper.vm.$nextTick();

        expect(wrapper.vm.fieldDefinition).toEqual('customFields.test');
    });

    it('should fire event when trigger value for boolean type', async () => {
        const wrapper = createWrapper(['product_stream.viewer'], 'boolean', 'equals', '', true);
        wrapper.vm.$nextTick();

        const productStreamValueSwitch = wrapper.find('.sw-product-stream-value');
        await productStreamValueSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        productStreamValueSwitch.find('.sw-select-option--1').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('boolean-change')).toBeTruthy();
    });

    it('should fire event with type \`equals\` when trigger value for boolean type YES', async () => {
        const wrapper = createWrapper(['product_stream.viewer'], 'boolean', 'equals', '', true);
        wrapper.vm.$nextTick();

        const productStreamValueSwitch = wrapper.find('.sw-product-stream-value');
        await productStreamValueSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        const productStreamValueYes = productStreamValueSwitch.findAll('.sw-select-result').at(0);

        expect(productStreamValueYes.text()).toBe('global.default.yes');
        productStreamValueYes.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('boolean-change')).toBeTruthy();
        expect(wrapper.emitted('boolean-change')[0][0].type).toEqual('equals');
        expect(wrapper.emitted('boolean-change')[0][0].value).toEqual('1');
    });

    it('should fire event with type \`not\` when trigger value for boolean type No', async () => {
        const wrapper = createWrapper(['product_stream.viewer'], 'boolean', 'equals', '', true);
        wrapper.vm.$nextTick();

        const productStreamValueSwitch = wrapper.find('.sw-product-stream-value');
        await productStreamValueSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        const productStreamValueNo = productStreamValueSwitch.findAll('.sw-select-result').at(1);

        expect(productStreamValueNo.text()).toBe('global.default.no');
        productStreamValueNo.trigger('click');

        expect(wrapper.emitted('boolean-change')).toBeTruthy();
        expect(wrapper.emitted('boolean-change')[0][0].type).toEqual('notEquals');
        expect(wrapper.emitted('boolean-change')[0][0].value).toEqual('0');
    });

    it('should fire events with correct types when trigger value for empty type changes', async () => {
        const wrapper = createWrapper(['product_stream.viewer'], 'empty', 'equals', '', true);
        wrapper.vm.$nextTick();

        const productStreamValueSwitch = wrapper.find('.sw-product-stream-value');
        await productStreamValueSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        let productStreamValueYes = productStreamValueSwitch.findAll('.sw-select-result').at(0);

        expect(productStreamValueYes.text()).toBe('global.default.yes');
        productStreamValueYes.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('empty-change')).toBeTruthy();
        expect(wrapper.emitted('empty-change')[0][0].type).toEqual('notEquals');

        await productStreamValueSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        productStreamValueYes = productStreamValueSwitch.findAll('.sw-select-result').at(1);

        expect(productStreamValueYes.text()).toBe('global.default.no');
        productStreamValueYes.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('empty-change')[1][0].type).toEqual('equals');
    });

    it('should return correct fieldDefinition with json accessor', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            fieldName: 'json.test',
            definition: {
                entity: 'product',
                getField: () => undefined,
                isJsonField: () => false
            }
        });
        wrapper.vm.$nextTick();

        expect(wrapper.vm.fieldDefinition).toEqual({
            value: 'json.test',
            type: 'string'
        });
    });

    it('should return empty filterType for foreign key field of manyToOne relation', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            fieldName: 'fkField',
            definition: {
                entity: 'product',
                getField: () => {
                    return {
                        type: 'uuid'
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
                            relation: 'many_to_one'
                        }
                    };

                    const result = {};
                    Object.keys(properties).forEach((propertyName) => {
                        if (filter(properties[propertyName]) === true) {
                            result[propertyName] = properties[propertyName];
                        }
                    });

                    return result;
                }
            }
        });
        wrapper.vm.$nextTick();

        expect(wrapper.vm.fieldType).toEqual('empty');
    });
});

