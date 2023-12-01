import { shallowMount } from '@vue/test-utils';
import swMediaBaseItem from 'src/app/asyncComponent/media/sw-media-base-item';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-icon';

Shopware.Component.register('sw-media-base-item', swMediaBaseItem);

// initial component setup
const setup = async (itemChanges = {}) => {
    const propsData = {
        item: {
            fileName: 'example',
            fileExtension: 'jpg',
            isLoading: false,
        },
    };
    propsData.item = { ...propsData.item, ...itemChanges };

    return shallowMount(await Shopware.Component.build('sw-media-base-item'), {
        stubs: {
            'sw-context-button': true,
            'sw-label': await Shopware.Component.build('sw-label'),
            'sw-icon': await Shopware.Component.build('sw-icon'),
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

        expect(wrapper.find('.icon--regular-AR').exists()).toBe(true);
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
