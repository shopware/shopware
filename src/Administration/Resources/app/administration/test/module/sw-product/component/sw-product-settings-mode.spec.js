import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-settings-mode';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';

describe('module/sw-product/component/sw-product-settings-mode', () => {
    function createWrapper() {
        const localVue = createLocalVue();
        localVue.directive('tooltip', {
            bind(el, binding) {
                el.setAttribute('tooltip-message', binding.value.message);
            }
        });

        return shallowMount(Shopware.Component.build('sw-product-settings-mode'), {
            localVue,
            mocks: {
                $route: {
                    name: 'sw.product.detail.base'
                }
            },

            stubs: {
                'sw-context-button': true,
                'sw-button': true,
                'sw-icon': true,
                'sw-switch-field': Shopware.Component.build('sw-switch-field'),
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-context-menu-divider': true,
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': true,
                'sw-loader': true
            },

            propsData: {
                modeSettings: {
                    value: {
                        advancedMode: {
                            label: 'sw-product.general.textAdvancedMode',
                            enabled: true
                        },
                        settings: [
                            {
                                key: 'general_information',
                                label: 'sw-product.detailBase.cardTitleProductInfo',
                                enabled: true,
                                name: 'general'
                            }
                        ]
                    }
                }
            }
        });
    }

    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show correct tooltip ', async () => {
        await wrapper.vm.$nextTick();

        const elementModeSettings = wrapper.find('.sw-product-settings-mode');
        expect(elementModeSettings.attributes()['tooltip-message']).toBe('sw-product.general.tooltipModeSettings');
    });

    it('should be able to switch advanced mode', async () => {
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            modeSettings: {
                value: {
                    advancedMode: {
                        label: 'sw-product.general.textAdvancedMode',
                        enabled: false
                    },
                    settings: [
                        {
                            key: 'general_information',
                            label: 'sw-product.detailBase.cardTitleProductInfo',
                            enabled: false,
                            name: 'general'
                        }
                    ]
                }
            }
        });

        const switchElement = wrapper.find('.sw-product-settings-mode__advanced-mode');
        expect(switchElement.exists()).toBe(true);

        const inputElement = wrapper.find('.sw-product-settings-mode__advanced-mode input[type="checkbox"]');
        await inputElement.trigger('click');

        expect(inputElement.element.checked).toBeTruthy();
    });

    it('should be able to check item settings ', async () => {
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            modeSettings: {
                value: {
                    advancedMode: {
                        label: 'sw-product.general.textAdvancedMode',
                        enabled: true
                    },
                    settings: [
                        {
                            key: 'general_information',
                            label: 'sw-product.detailBase.cardTitleProductInfo',
                            enabled: false,
                            name: 'general'
                        }
                    ]
                }
            }
        });

        const elementItem = wrapper.find('.sw-product-settings-mode__item');
        expect(elementItem.exists()).toBe(true);

        const checkboxElement = wrapper.find('.sw-product-settings-mode__item input[type="checkbox"]');
        await checkboxElement.trigger('click');

        expect(checkboxElement.element.checked).toBeTruthy();
    });

    it('should be disabled the item settings when the advanced mode is disabled ', async () => {
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            modeSettings: {
                value: {
                    advancedMode: {
                        label: 'sw-product.general.textAdvancedMode',
                        enabled: true
                    },
                    settings: [
                        {
                            key: 'general_information',
                            label: 'sw-product.detailBase.cardTitleProductInfo',
                            enabled: true,
                            name: 'general'
                        }
                    ]
                }
            }
        });

        const inputElement = wrapper.find('.sw-product-settings-mode__advanced-mode input[type="checkbox"]');
        await inputElement.trigger('click');

        const checkboxElement = wrapper.find('.sw-product-settings-mode__item input[type="checkbox"]');
        expect(checkboxElement.attributes().disabled).toBe('disabled');
    });
});
