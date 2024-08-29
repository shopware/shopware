/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import { reactive } from 'vue';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-cms-slot', {
        sync: true,
    }), {
        props: {
            element: {
                type: 'example_cms_element_type',
            },
            ...props,
        },
        global: {
            stubs: {
                'foo-bar': true,
                'sw-icon': true,
                'sw-modal': true,
                'sw-skeleton-bar': true,
                'sw-button': true,
                'sw-sidebar-collapse': true,
            },
            provide: {
                cmsService: {
                    getCmsServiceState: () => reactive({
                        elementRegistry: {
                            product_list_block: null,
                            landing_block: null,
                            example_cms_element_type: {
                                component: 'foo-bar',
                                disabledConfigInfoTextKey: 'lorem',
                                defaultConfig: {
                                    text: 'lorem',
                                },
                            },
                        },
                    }),
                    getCmsElementRegistry: () => {
                        return {
                            product_list_block: null,
                            landing_block: null,
                        };
                    },
                    isElementAllowedInPageType: (name, pageType) => name.startsWith(pageType),
                },
                cmsElementFavorites: {
                    isFavorite() {
                        return false;
                    },
                },
            },
        },
    });
}

jest.useFakeTimers();

describe('module/sw-cms/component/sw-cms-slot', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => ({
                isSystemDefaultLanguage: true,
                currentPageType: 'product_list',
            }),
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the slot name as class', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                slot: 'left',
            },
        });

        expect(wrapper.classes()).toContain('sw-cms-slot-left');
    });

    it('disable the custom component', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.classes()).toContain('is--disabled');

        const customComponent = wrapper.find('foo-bar-stub');
        expect(customComponent.attributes().disabled).toBe('true');
    });

    it('enable the custom component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');

        const customComponent = wrapper.find('foo-bar-stub');
        expect(customComponent.attributes().disabled).toBeUndefined();
    });

    it('disable the slot setting and show tooltip when element is locked', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                type: 'example_cms_element_type',
                locked: true,
            },
            active: true,
        });

        expect(wrapper.find('.sw-cms-slot__settings-action').classes()).toContain('is--disabled');
        expect(wrapper.vm.tooltipDisabled.disabled).toBe(false);
    });

    it('has modalVariant "large" if element type is not "html"', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.modalVariant).toBe('large');
    });

    it('has modalVariant "full" if element type is "html"', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                type: 'html',
            },
        });

        expect(wrapper.vm.modalVariant).toBe('full');
    });

    it('test onSelectElement', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.element).toEqual({
            type: 'example_cms_element_type',
        });

        wrapper.vm.onSelectElement({
            name: 'testElement',
        });
        expect(wrapper.vm.element).toEqual({
            type: 'testElement',
            config: {},
            data: {},
            locked: false,
        });

        wrapper.vm.onSelectElement({
            name: 'testElement2',
            defaultConfig: {
                imageId: 1234567980,
            },
        });
        expect(wrapper.vm.element).toEqual({
            type: 'testElement2',
            config: {
                imageId: 1234567980,
            },
            data: {},
            locked: false,
        });

        wrapper.vm.onSelectElement({
            name: 'testElement3',
            defaultData: {
                text: 'Test text',
            },
        });
        expect(wrapper.vm.element).toEqual({
            type: 'testElement3',
            config: {},
            data: {
                text: 'Test text',
            },
            locked: false,
        });

        wrapper.vm.onSelectElement({
            name: 'testElement4',
            defaultConfig: {
                imageId: 1234567980,
            },
            defaultData: {
                text: 'Test text',
            },
        });
        expect(wrapper.vm.element).toEqual({
            type: 'testElement4',
            config: {
                imageId: 1234567980,
            },
            data: {
                text: 'Test text',
            },
            locked: false,
        });
    });

    it('should reset previous translated config values on onSelectElement', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.element.translated = {
            config: {
                test: 'test',
            },
        };

        wrapper.vm.onSelectElement({
            name: 'newTestElement',
        });

        expect(wrapper.vm.element.type).toBe('newTestElement');
        expect(wrapper.vm.element.translated.config).toEqual({});
    });

    it('should filter blocks based on pageType compatibility', async () => {
        const wrapper = await createWrapper();

        expect(Object.keys(wrapper.vm.cmsElements)).toStrictEqual(['product_list_block']);
    });

    it('should show an error state after 10s when element is not existing', async () => {
        const wrapper = await createWrapper({
            element: {
                type: 'not-existing',
            },
        });

        // Element not found should not be visible
        expect(wrapper.find('.sw-cms-slot__element-not-found').exists()).toBe(false);
        // Loading skeleton should be visible
        expect(wrapper.find('sw-skeleton-bar-stub').exists()).toBe(true);

        // Advance time by 10s
        jest.advanceTimersByTime(10000);
        await flushPromises();

        // Element not found should be visible after 10 seconds
        expect(wrapper.find('.sw-cms-slot__element-not-found').exists()).toBe(true);
        // Loading skeleton should not be visible after 10 seconds
        expect(wrapper.find('sw-skeleton-bar-stub').exists()).toBe(false);
    });
});
