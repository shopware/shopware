/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import uuid from 'src/../test/_helper_/uuid';
import componentConfig from 'src/module/sw-media/component/sidebar/sw-media-sidebar';

Shopware.Component.register('sw-media-sidebar', componentConfig);

async function createWrapper(items, mediaRepositoryFunctions = {}) {
    return shallowMount(await Shopware.Component.build('sw-media-sidebar'), {
        stubs: {
            'sw-media-quickinfo': {
                template: `
                    <button class='sw-media-quickinfo' @click="modifyItem"></button>`,
                props: {
                    item: {
                        required: true,
                        type: Object,
                        default: {},
                    },
                },

                methods: {
                    modifyItem() {
                        const { item } = this;
                        item.fileName = 'a-new-name.glb';

                        this.$emit('update:item', item);
                    },
                },
            },
            'sw-media-folder-info': true,
            'sw-media-quickinfo-multiple': true,
            'sw-empty-state': true,
        },

        propsData: {
            items: items,
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    save: () => Promise.resolve(true),
                    ...mediaRepositoryFunctions,
                }),
            },
        },
    });
}

const defaultNames = ['t-shirt.png', 'flask.jpg', 'router.glb'];
const createItems = (itemNames = defaultNames) => {
    return itemNames.map((name) => {
        return {
            getEntityName: () => {
                return 'media';
            },
            id: uuid.get(name),
            fileName: name,
            avatarUsers: [],
            categories: [],
            productManufacturers: [],
            productMedia: [],
            mailTemplateMedia: [],
            documentBaseConfigs: [],
            paymentMethods: [],
            shippingMethods: [],
        };
    });
};


describe('module/sw-media/component/sidebar/sw-media-sidebar', () => {
    it('should save item data when receiving item:update event', async () => {
        const mediaItems = createItems(['router.glb']);
        const mediaSaveMock = jest.fn();
        const mediaRepositoryFunctions = {
            save: mediaSaveMock,
        };

        const wrapper = await createWrapper(mediaItems, mediaRepositoryFunctions);
        await wrapper.vm.$nextTick();
        const mediaQuickInfo = wrapper.find('.sw-media-quickinfo');
        expect(mediaQuickInfo.exists()).toBe(true);

        await mediaQuickInfo.trigger('click');
        await mediaQuickInfo.vm.$nextTick();
        expect(mediaQuickInfo.emitted('update:item')).toBeTruthy();

        expect(mediaSaveMock).toHaveBeenCalled();
        expect(mediaSaveMock).toHaveBeenCalledWith(
            expect.objectContaining({
                id: uuid.get('router.glb'),
                fileName: 'a-new-name.glb',
            }),
            expect.any(Object),
        );
    });
});
