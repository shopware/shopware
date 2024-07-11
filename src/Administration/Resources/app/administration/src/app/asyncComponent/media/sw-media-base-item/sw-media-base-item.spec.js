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
                'sw-label': await wrapTestComponent('sw-label', { sync: true }),
                'sw-icon': await wrapTestComponent('sw-icon', { sync: true }),
                'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated', { sync: true }),
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
});
