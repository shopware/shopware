/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const mockHandleUpdateContent = jest.fn();

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
                'foo-bar': {
                    template: `
                        <div class="foo-bar">
                            <slot></slot>
                        </div>
                    `,
                    methods: {
                        handleUpdateContent: () => {
                            mockHandleUpdateContent();
                        },
                    },
                },
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                             <slot></slot>
                        </div>
                    `,
                },
                'sw-sidebar-collapse': true,
                'sw-skeleton-bar': true,
                'sw-button': true,
                'sw-icon': true,
            },
            provide: {
                cmsService: Shopware.Service('cmsService'),
                cmsElementFavorites: Shopware.Service('cmsElementFavorites'),
            },
        },
    });
}

jest.useFakeTimers();

describe('module/sw-cms/component/sw-cms-slot', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        const cmsService = Shopware.Service('cmsService');

        const buildConfig = (name, parameters = {}) => {
            return {
                name,
                component: 'foo-bar',
                configComponent: 'foo-bar',
                flag: true,
                allowedPageTypes: ['product_list'],
                ...parameters,
            };
        };

        cmsService.registerCmsElement(buildConfig('product_list_slot'));
        cmsService.registerCmsElement(buildConfig('product_detail_slot', {
            allowedPageTypes: ['product_detail'],
        }));
        cmsService.registerCmsElement(buildConfig('product_detail_slot2', {
            allowedPageTypes: ['product_detail'],
        }));
        cmsService.registerCmsElement(buildConfig('product_detail_slot3', {
            allowedPageTypes: ['product_detail'],
        }));
        cmsService.registerCmsElement(buildConfig('landing_slot', {
            allowedPageTypes: ['landingpage'],
        }));
        cmsService.registerCmsElement(buildConfig(
            'example_cms_element_type',
            {
                allowedPageTypes: ['landingpage'],
                disabledConfigInfoTextKey: 'lorem',
                defaultConfig: {
                    text: 'lorem',
                },
            },
        ));
        cmsService.registerCmsElement(buildConfig(
            'with_locked',
            {
                allowedPageTypes: ['landingpage'],
                defaultConfig: {
                    text: 'lorem',
                },
                locked: true,
            },
        ));
        cmsService.registerCmsElement(buildConfig(
            'without_default_config',
            {
                allowedPageTypes: ['landingpage'],
                locked: false,
            },
        ));
        cmsService.registerCmsElement(buildConfig(
            'with_config_and_unlocked',
            {
                allowedPageTypes: ['landingpage'],
                defaultConfig: {
                    text: 'lorem',
                },
                locked: false,
            },
        ));
    });

    beforeEach(() => {
        jest.clearAllMocks();

        const store = Shopware.Store.get('cmsPage');
        store.resetCmsPageState();
        store.currentPageType = 'product_list';

        const cmsElementFavorites = Shopware.Service('cmsElementFavorites');
        cmsElementFavorites.getFavoriteElementNames().forEach((favorite) => {
            cmsElementFavorites.update(false, favorite);
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

    it('should disable the custom component', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.classes()).toContain('is--disabled');

        const customComponent = wrapper.find('.foo-bar');
        expect(customComponent.attributes().disabled).toBe('true');
    });

    it('should enable the custom component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');

        const customComponent = wrapper.find('.foo-bar');
        expect(customComponent.attributes().disabled).toBeUndefined();
    });

    it('should show a tooltip when the element is not disabled', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                type: 'with_locked',
                locked: false,
            },
            active: true,
        });

        expect(wrapper.find('.sw-cms-slot__settings-action').classes()).not.toContain('is--disabled');
        expect(wrapper.vm.tooltipDisabled.message).toBe('sw-cms.elements.general.config.tab.settings');
        expect(wrapper.vm.tooltipDisabled.disabled).toBe(true);
    });

    it('should disable the slot setting and show a tooltip when the element is locked', async () => {
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

    it('should collect grouped CMS elements, depending on favorites', async () => {
        const store = Shopware.Store.get('cmsPage');
        store.currentPageType = 'product_detail';

        const wrapper = await createWrapper();
        wrapper.vm.cmsElementFavorites.update(true, 'product_detail_slot');
        wrapper.vm.cmsElementFavorites.update(true, 'product_detail_slot2');

        await wrapper.setProps({
            element: {
                type: 'product_detail',
                locked: true,
            },
            active: true,
        });

        const actualFavorites = wrapper.vm.groupedCmsElements
            .find(group => group.title === 'sw-cms.elements.general.switch.groups.favorites');
        expect(actualFavorites.items).toHaveLength(2);
        expect(actualFavorites.items[0].name).toBe('product_detail_slot');
        expect(actualFavorites.items[1].name).toBe('product_detail_slot2');

        const actualNonFavorites = wrapper.vm.groupedCmsElements
            .find(group => group.title === 'sw-cms.elements.general.switch.groups.all');
        expect(actualNonFavorites.items).toHaveLength(1);
        expect(actualNonFavorites.items[0].name).toBe('product_detail_slot3');
    });

    it('should have modalVariant "large" if the element type is not "html"', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.modalVariant).toBe('large');
    });

    it('should have modalVariant "full" if the element type is "html"', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                type: 'html',
            },
        });

        expect(wrapper.vm.modalVariant).toBe('full');
    });

    const onSelectElementDataProvider = [
        [
            'having just a name',
            {
                name: 'testElement',
            }, {
                type: 'testElement',
                config: {},
                data: {},
                locked: false,
            },
        ], [
            'with a defaultConfig',
            {
                name: 'testElement',
                defaultConfig: {
                    imageId: 1234567980,
                },
            }, {
                type: 'testElement',
                config: {
                    imageId: 1234567980,
                },
                data: {},
                locked: false,
            },
        ], [
            'with defaultData set',
            {
                name: 'testElement',
                defaultData: {
                    text: 'Test text',
                },
            }, {
                type: 'testElement',
                config: {},
                data: {
                    text: 'Test text',
                },
                locked: false,
            },
        ], [
            'with defaultConfig and defaultData set',
            {
                name: 'testElement',
                defaultConfig: {
                    imageId: 1234567980,
                },
                defaultData: {
                    text: 'Test text',
                },
            }, {
                type: 'testElement',
                config: {
                    imageId: 1234567980,
                },
                data: {
                    text: 'Test text',
                },
                locked: false,
            },
        ],
    ];
    it.each(onSelectElementDataProvider)('should validate the results of onSelectElement %s', async (caseName, actual, expected) => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.element).toEqual({
            type: 'example_cms_element_type',
        });

        wrapper.vm.onSelectElement(actual);
        expect(wrapper.vm.element).toEqual(expected);
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

    it('should filter slots based on pageType compatibility', async () => {
        const wrapper = await createWrapper();

        expect(Object.keys(wrapper.vm.cmsElements)).toStrictEqual(['product_list_slot']);
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

    const toggleElementSelectionModalDataProvider = [
        ['onElementButtonClick', true],
        ['onCloseElementModal', false],
    ];
    it.each(toggleElementSelectionModalDataProvider)('should toggle the element selection modal according to %s', async (toggleMethod, expected) => {
        const wrapper = await createWrapper();

        wrapper.vm[toggleMethod]();
        expect(wrapper.vm.showElementSelection).toBe(expected);
    });

    it.each([true, false])('should not toggle the element settings modal without defaultConfig and showElementSettings is %s', async (actualShowElementSettings) => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            showElementSettings: actualShowElementSettings,
        });
        await wrapper.setProps({
            element: {
                type: 'without_default_config',
                locked: false,
            },
        });

        expect(wrapper.vm.showElementSettings).toBe(actualShowElementSettings);
        wrapper.vm.onSettingsButtonClick();
        expect(wrapper.vm.showElementSettings).toBe(actualShowElementSettings);
    });

    it.each([true, false])('should not toggle the element settings modal with a locked element and showElementSettings is %s', async (actualShowElementSettings) => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            showElementSettings: actualShowElementSettings,
        });
        await wrapper.setProps({
            element: {
                type: 'with_locked',
                locked: true,
            },
        });

        expect(wrapper.vm.showElementSettings).toBe(actualShowElementSettings);
        wrapper.vm.onSettingsButtonClick();
        expect(wrapper.vm.showElementSettings).toBe(actualShowElementSettings);
    });

    it.each([true, false])('should show the element settings modal with a defaultConfig, no locked element and showElementSettings is %s', async (actualShowElementSettings) => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            showElementSettings: actualShowElementSettings,
        });
        await wrapper.setProps({
            showElementSettings: false,
            element: {
                type: 'with_config_and_unlocked',
                locked: false,
            },
        });

        expect(wrapper.vm.showElementSettings).toBe(actualShowElementSettings);
        wrapper.vm.onSettingsButtonClick();
        expect(wrapper.vm.showElementSettings).toBe(true);
    });

    it('should close the settings modal and call handleUpdateContent if the methods exists and showElementSettings is true', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                type: 'with_config_and_unlocked',
            },
        });
        await wrapper.setData({
            showElementSettings: true,
        });
        await flushPromises();

        expect(wrapper.vm.showElementSettings).toBe(true);
        wrapper.vm.onCloseSettingsModal();
        expect(wrapper.vm.showElementSettings).toBe(false);
        expect(mockHandleUpdateContent).toHaveBeenCalledTimes(1);
    });

    it('should close the settings modal and call handleUpdateContent if the methods exists and showElementSettings is false', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                type: 'with_config_and_unlocked',
            },
        });
        await wrapper.setData({
            showElementSettings: false,
        });
        await flushPromises();

        expect(wrapper.vm.showElementSettings).toBe(false);
        wrapper.vm.onCloseSettingsModal();
        expect(wrapper.vm.showElementSettings).toBe(false);
        expect(mockHandleUpdateContent).not.toHaveBeenCalled();
    });

    it('should toggle the element being favorite', async () => {
        const wrapper = await createWrapper();
        const elementName = 'example_cms_element_type';
        await wrapper.setProps({
            element: {
                type: elementName,
            },
        });

        expect(wrapper.vm.cmsElementFavorites.isFavorite(elementName)).toBe(false);

        wrapper.vm.onToggleElementFavorite(elementName);
        expect(wrapper.vm.cmsElementFavorites.isFavorite(elementName)).toBe(true);

        wrapper.vm.onToggleElementFavorite(elementName);
        expect(wrapper.vm.cmsElementFavorites.isFavorite(elementName)).toBe(false);
    });

    it('should group elements correctly depending on being a favorite or not', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                type: 'product_detail',
            },
        });

        expect(wrapper.vm.elementInElementGroup({ name: 'product_detail_slot' }, 'favorite')).toBe(false);

        wrapper.vm.cmsElementFavorites.update(true, 'product_detail_slot');
        expect(wrapper.vm.elementInElementGroup({ name: 'product_detail_slot' }, 'favorite')).toBe(true);
    });

    it('should not group elements, when they are not favorites', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                type: 'product_detail',
            },
        });

        expect(wrapper.vm.elementInElementGroup({ name: 'product_detail_slot' }, 'whatever')).toBe(true);
    });
});
