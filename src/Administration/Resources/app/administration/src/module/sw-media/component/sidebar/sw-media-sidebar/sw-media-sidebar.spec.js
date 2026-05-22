/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import uuid from 'test/_helper_/uuid';

async function createWrapper(items, mediaRepositoryFunctions = {}) {
    return mount(await wrapTestComponent('sw-media-sidebar', { sync: true }), {
        global: {
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

            provide: {
                repositoryFactory: {
                    create: () => ({
                        save: () => Promise.resolve(true),
                        ...mediaRepositoryFunctions,
                    }),
                },
            },
        },

        props: {
            items: items,
        },
    });
}

const defaultNames = [
    't-shirt.png',
    'flask.jpg',
    'router.glb',
];
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
    it('should show the selected media headline without a duplicate file extension', async () => {
        const mediaItems = [
            {
                getEntityName: () => 'media',
                id: uuid.get('product-image'),
                fileName: 'product-image.png',
                fileExtension: 'png',
                avatarUsers: [],
                categories: [],
                productManufacturers: [],
                productMedia: [],
                mailTemplateMedia: [],
                documentBaseConfigs: [],
                paymentMethods: [],
                shippingMethods: [],
            },
        ];

        const wrapper = await createWrapper(mediaItems);

        expect(wrapper.vm.headLine).toBe('product-image');
    });

    it('should split long selected media headlines for middle truncation', async () => {
        const mediaItems = [
            {
                getEntityName: () => 'media',
                id: uuid.get('admin-notifications'),
                fileName: 'admin-notifications-close-outside-click-with-cursor',
                fileExtension: 'gif',
                avatarUsers: [],
                categories: [],
                productManufacturers: [],
                productMedia: [],
                mailTemplateMedia: [],
                documentBaseConfigs: [],
                paymentMethods: [],
                shippingMethods: [],
            },
        ];

        const wrapper = await createWrapper(mediaItems);

        expect(wrapper.vm.headLineParts).toStrictEqual({
            start: 'admin-notifications-close-outside-click',
            end: '-with-cursor',
        });
    });

    it('should save item data when receiving item:update event', async () => {
        const mediaItems = createItems(['router.glb']);
        const mediaSaveMock = jest.fn();
        const mediaRepositoryFunctions = {
            save: mediaSaveMock,
        };

        const wrapper = await createWrapper(mediaItems, mediaRepositoryFunctions);
        await wrapper.vm.$nextTick();
        const mediaQuickInfo = wrapper.findComponent('.sw-media-quickinfo');
        expect(mediaQuickInfo.exists()).toBe(true);

        await mediaQuickInfo.trigger('click');
        await flushPromises();
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
