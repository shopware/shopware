/*
 * @package inventory
 */

import { mount } from '@vue/test-utils_v3';

describe('module/sw-product/component/sw-product-settings-mode', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-product-settings-mode', { sync: true }), {
            attachTo: document.body,
            props: {
                modeSettings: {
                    value: {
                        advancedMode: {
                            label: 'sw-product.general.textAdvancedMode',
                            enabled: true,
                        },
                        settings: [
                            {
                                key: 'general_information',
                                label: 'sw-product.detailBase.cardTitleProductInfo',
                                enabled: true,
                                name: 'general',
                            },
                        ],
                    },
                },
            },
            global: {
                directives: {
                    tooltip: {
                        bind(el, binding) {
                            el.setAttribute('tooltip-message', binding.value.message);
                        },
                    },
                },
                mocks: {
                    $route: {
                        name: 'sw.product.detail.base',
                    },
                },
                stubs: {
                    'sw-context-button': await wrapTestComponent('sw-context-button'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-icon': true,
                    'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-context-menu-divider': await wrapTestComponent('sw-context-menu-divider'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-loader': true,
                },
            },
        });
    }

    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show correct tooltip', async () => {
        const elementModeSettings = wrapper.get('.sw-product-settings-mode');
        expect(elementModeSettings.attributes()['tooltip-message']).toBe('sw-product.general.tooltipModeSettings');
    });

    it('should be able to switch advanced mode', async () => {
        await wrapper.setProps({
            modeSettings: {
                value: {
                    advancedMode: {
                        label: 'sw-product.general.textAdvancedMode',
                        enabled: false,
                    },
                    settings: [
                        {
                            key: 'general_information',
                            label: 'sw-product.detailBase.cardTitleProductInfo',
                            enabled: false,
                            name: 'general',
                        },
                    ],
                },
            },
        });
        await flushPromises();

        const button = wrapper.get('.sw-product-settings-mode__trigger');
        await button.trigger('click');
        await flushPromises();

        const switchElement = document.body.querySelector('.sw-product-settings-mode__advanced-mode');
        expect(switchElement).toBeInTheDocument();

        const inputElement = document.body.querySelector('.sw-product-settings-mode__advanced-mode input[type="checkbox"]');
        await inputElement.click();

        expect(inputElement.value).toBe('on');
    });

    it('should be able to check item settings', async () => {
        await wrapper.setProps({
            modeSettings: {
                value: {
                    advancedMode: {
                        label: 'sw-product.general.textAdvancedMode',
                        enabled: true,
                    },
                    settings: [
                        {
                            key: 'general_information',
                            label: 'sw-product.detailBase.cardTitleProductInfo',
                            enabled: false,
                            name: 'general',
                        },
                    ],
                },
            },
        });
        await flushPromises();

        const button = wrapper.get('.sw-product-settings-mode__trigger');
        await button.trigger('click');
        await flushPromises();

        const elementItem = document.body.querySelector('.sw-product-settings-mode__item');
        expect(elementItem).toBeInTheDocument();

        const checkboxElement = document.body.querySelector('.sw-product-settings-mode__item input[type="checkbox"]');
        await checkboxElement.click();

        expect(checkboxElement.value).toBe('on');
    });

    it('should be disabled the item settings when the advanced mode is disabled', async () => {
        await wrapper.setProps({
            modeSettings: {
                value: {
                    advancedMode: {
                        label: 'sw-product.general.textAdvancedMode',
                        enabled: true,
                    },
                    settings: [
                        {
                            key: 'general_information',
                            label: 'sw-product.detailBase.cardTitleProductInfo',
                            enabled: true,
                            name: 'general',
                        },
                    ],
                },
            },
        });
        await flushPromises();

        const button = wrapper.get('.sw-product-settings-mode__trigger');
        await button.trigger('click');
        await flushPromises();

        const inputElement = document.body.querySelector('.sw-product-settings-mode__advanced-mode input[type="checkbox"]');
        await inputElement.click();

        const checkboxElement = document.body.querySelector('.sw-product-settings-mode__item input[type="checkbox"]');
        expect(checkboxElement.disabled).toBe(true);
    });
});
