/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const setup = async (itemChanges = {}) => {
    const propsData = {
        item: {
            fileName: 'example',
            fileExtension: 'jpg',
            isLoading: false,
        },
    };
    propsData.item = { ...propsData.item, ...itemChanges };

    return mount(await wrapTestComponent('sw-media-base-item', { sync: true }), {
        global: {
            stubs: {
                'sw-context-button': true,
                'sw-label': await wrapTestComponent('sw-label', {
                    sync: true,
                }),
                'sw-color-badge': true,
                'mt-checkbox': true,
            },
            provide: {
                systemConfigApiService: {
                    getValues: () => {
                        return Promise.resolve({});
                    },
                },
            },
        },
        propsData,
    });
};

describe('src/app/asyncComponent/media/sw-media-base-item', () => {
    it('should show icon--regular-AR if spatial objet is AR ready', async () => {
        const wrapper = await setup({
            fileExtension: 'glb',
            config: {
                spatial: {
                    arReady: true,
                },
            },
        });
        expect(wrapper.find('.icon--regular-AR').exists()).toBeTruthy();
    });

    it('should show icon--regular-3d if the spatial object is not ready to use in AR', async () => {
        const wrapper = await setup({
            fileExtension: 'glb',
            config: {
                spatial: {
                    arReady: false,
                },
            },
        });

        expect(wrapper.find('.icon--regular-AR').exists()).toBe(false);
        expect(wrapper.find('.icon--regular-3d').exists()).toBe(true);
    });

    it('should check item.url if item.fileExtension is not defined', async () => {
        const wrapper = await setup({
            fileExtension: undefined,
            config: {
                spatial: {
                    arReady: false,
                },
            },
            url: 'http://test/example.glb',
        });

        expect(wrapper.find('.icon--regular-3d').exists()).toBe(true);
    });

    it('should not show any icon if item is not a spatial object', async () => {
        const wrapper = await setup();

        expect(wrapper.find('.icon--regular-AR').exists()).toBe(false);
        expect(wrapper.find('.icon--regular-3d').exists()).toBe(false);
    });

    it('should show a GIF label for gif files', async () => {
        const wrapper = await setup({
            fileExtension: 'gif',
        });

        expect(wrapper.find('.sw-media-base-item__labels-text').text()).toBe('GIF');
        expect(wrapper.find('.icon--regular-AR').exists()).toBe(false);
        expect(wrapper.find('.icon--regular-3d').exists()).toBe(false);
    });

    it('should show a GIF label for image/gif mime type', async () => {
        const wrapper = await setup({
            fileExtension: undefined,
            mimeType: 'image/gif',
        });

        expect(wrapper.find('.sw-media-base-item__labels-text').text()).toBe('GIF');
    });

    it('should provide an accessible name for media files', async () => {
        const wrapper = await setup();

        expect(wrapper.attributes('aria-label')).toBe('example.jpg');
    });

    it('should provide an accessible name for folders', async () => {
        const wrapper = await setup({
            fileName: undefined,
            fileExtension: undefined,
            name: 'Product Media',
        });

        expect(wrapper.attributes('aria-label')).toBe('Product Media');
    });

    it('should emit item click when space is pressed on the item', async () => {
        const wrapper = await setup();

        await wrapper.trigger('keydown.space');

        expect(wrapper.emitted('media-item-click')).toHaveLength(1);
    });

    it('should expose disabled state to assistive technology', async () => {
        const wrapper = await setup();

        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.attributes('aria-disabled')).toBe('true');
    });

    it('should show list metadata without a context menu button', async () => {
        const wrapper = await mount(await wrapTestComponent('sw-media-base-item', { sync: true }), {
            global: {
                stubs: {
                    'sw-context-button': true,
                    'sw-label': await wrapTestComponent('sw-label', {
                        sync: true,
                    }),
                    'sw-color-badge': true,
                    'mt-checkbox': true,
                },
                provide: {
                    systemConfigApiService: {
                        getValues: () => {
                            return Promise.resolve({});
                        },
                    },
                },
            },
            propsData: {
                item: {
                    fileName: 'example',
                    fileExtension: 'jpg',
                    isLoading: false,
                },
                isList: true,
                showContextMenuButton: false,
            },
            slots: {
                metadata: '<span class="test-metadata">21 May 2026</span>',
            },
        });

        expect(wrapper.find('.sw-media-base-item__metadata-container').exists()).toBe(true);
        expect(wrapper.find('.test-metadata').text()).toBe('21 May 2026');
    });
});
