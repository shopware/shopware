/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const mockSnackbar = {
    addSnackbar: jest.fn(),
};

jest.mock('@shopware-ag/meteor-component-library', () => ({
    useSnackbar: () => mockSnackbar,
}));

describe('components/media/sw-media-modal-delete', () => {
    const itemDeleteMock = (options = {}) => {
        return {
            getEntityName: () => {
                return 'media';
            },
            id: 'z928dj393n83o02092dh292hjd92',
            fileName: 'demo.jpg',
            avatarUsers: {},
            categories: [],
            productManufacturers: [],
            productMedia: [],
            mailTemplateMedia: [],
            documentBaseConfigs: [],
            paymentMethods: [],
            shippingMethods: [],
            cmsBlocks: [],
            cmsSections: [],
            cmsPages: [],
            ...options,
        };
    };

    beforeEach(() => {
        jest.clearAllMocks();
    });

    const createWrapper = async (itemDeleteOptions = null, deleteMock = jest.fn(() => Promise.resolve())) => {
        const itemsToDelete = itemDeleteOptions || [itemDeleteMock()];

        return mount(await wrapTestComponent('sw-media-modal-delete', { sync: true }), {
            props: { itemsToDelete },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-modal': true,
                    'sw-media-quickinfo-usage': {
                        template: '<div class="sw-media-quickinfo-usage"></div>',
                    },
                    'sw-media-media-item': {
                        template: '<div class="sw-media-media-item"></div>',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            delete: deleteMock,
                            search: () => Promise.resolve(),
                        }),
                    },
                },
                mocks: {
                    $t: (key, ...values) => {
                        if (key === 'global.sw-media-modal-delete.notification.errorSingle.message.media') {
                            const params = values[0];
                            return `Error deleting file "${params?.name ?? ''}".`;
                        }

                        if (key === 'global.sw-media-modal-delete.notification.errorSingle.fallback.media') {
                            return 'selected file';
                        }

                        if (key === 'global.sw-media-modal-delete.notification.successOverall.message.media') {
                            const params = values[0];
                            return `${params?.count}/${params?.total} files deleted.`;
                        }

                        return key;
                    },
                },
            },
        });
    };

    it('should return list of Media with only one media selected', async () => {
        const productMediaMock = {
            id: '3093dl23hf83jh29d0jsj',
            product: {
                id: '1d20d83hjd1lsndoso9shj0',
                description: 'Sint illo iste ipsum. ',
                name: 'Incredible Marble Clean Music',
            },
        };

        const wrapper = await createWrapper([
            itemDeleteMock({ productMedia: [productMediaMock] }),
        ]);

        expect(wrapper.vm.mediaQuickInfo.fileName).toMatch(itemDeleteMock().fileName);
        expect(wrapper.vm.mediaQuickInfo.productMedia).toEqual([
            productMediaMock,
        ]);
        expect(wrapper.vm.mediaInUsages).toHaveLength(0);
        expect(wrapper.find('.sw-media-quickinfo-usage').exists()).toBeTruthy();
        expect(wrapper.find('.sw-media-media-item').exists()).toBeFalsy();
    });

    it('should return list of Media quick info usage with all of places already linked', async () => {
        const avatarUser = { id: '2d221dd5z2d2d90389fj1d21' };
        const category = { id: '30f832hdk5bn383h23023hf02' };
        const productManufacturer = { id: '923hf9202jd02j29d72h20' };
        const mailTemplateMedia = { id: '292hf92h283f89303h20210f' };
        const documentBaseConfig = { id: '94hf02hnf02hf82292hf0202f' };
        const paymentMethod = { id: '02j2j0f02h2f0283nhf834h239f2' };
        const shippingMethod = { id: '02jhf92jf784jflsnhffi9989' };
        const productMedia = { id: 'f83hf3dn2k5nv83020283jf9320' };
        const cmsBlock = {
            section: {
                pageId: 'cmsBlockId',
                page: { translated: { name: 'cms block test' } },
            },
        };
        const cmsSection = {
            pageId: 'cmsSectionId',
            page: { translated: { name: 'cms section test' } },
        };
        const cmsPage = {
            id: 'cmsPageId',
            translated: { name: 'cms page test' },
        };

        const wrapper = await createWrapper([
            itemDeleteMock({
                id: '2028dh992hd021jdj0202j',
                avatarUsers: [avatarUser],
                categories: [category],
                productManufacturers: [productManufacturer],
                productMedia: [productMedia],
                mailTemplateMedia: [mailTemplateMedia],
                documentBaseConfigs: [documentBaseConfig],
                paymentMethods: [paymentMethod],
                shippingMethods: [shippingMethod],
                cmsBlocks: [cmsBlock],
                cmsSections: [cmsSection],
                cmsPages: [cmsPage],
            }),
        ]);

        expect(wrapper.vm.mediaQuickInfo.fileName).toMatch(itemDeleteMock().fileName);
        expect(wrapper.vm.mediaInUsages).toHaveLength(0);
        expect(wrapper.vm.mediaQuickInfo.avatarUsers).toEqual([avatarUser]);
        expect(wrapper.vm.mediaQuickInfo.categories).toEqual([category]);
        expect(wrapper.vm.mediaQuickInfo.productManufacturers).toEqual([
            productManufacturer,
        ]);
        expect(wrapper.vm.mediaQuickInfo.productMedia).toEqual([productMedia]);
        expect(wrapper.vm.mediaQuickInfo.mailTemplateMedia).toEqual([
            mailTemplateMedia,
        ]);
        expect(wrapper.vm.mediaQuickInfo.documentBaseConfigs).toEqual([
            documentBaseConfig,
        ]);
        expect(wrapper.vm.mediaQuickInfo.paymentMethods).toEqual([
            paymentMethod,
        ]);
        expect(wrapper.vm.mediaQuickInfo.shippingMethods).toEqual([
            shippingMethod,
        ]);
        expect(wrapper.vm.mediaQuickInfo.cmsBlocks).toEqual([cmsBlock]);
        expect(wrapper.vm.mediaQuickInfo.cmsSections).toEqual([cmsSection]);
        expect(wrapper.vm.mediaQuickInfo.cmsPages).toEqual([cmsPage]);
        expect(wrapper.find('.sw-media-quickinfo-usage').exists()).toBeTruthy();
        expect(wrapper.find('.sw-media-media-item').exists()).toBeFalsy();
    });

    it('should return list of File have media in usage with more than one media selected', async () => {
        const productMediaMock = {
            id: '02djd92jdj1d928djc93333nf90',
            product: {
                id: 'ds239djd29fj39fj30243d2d222d',
                description: 'Sint illo iste ipsum. ',
                name: 'Incredible Marble Clean Music',
            },
        };

        const manyMediaItemMock = [
            itemDeleteMock({
                id: '28dh2xi3jw455g5sd331d',
                productMedia: [productMediaMock],
            }),
            itemDeleteMock({
                id: 'ff32ff112d3t4gf2g44rd',
                productMedia: [productMediaMock],
            }),
        ];

        const wrapper = await createWrapper(manyMediaItemMock);
        expect(wrapper.vm.mediaInUsages).toHaveLength(manyMediaItemMock.length);
        wrapper.vm.mediaInUsages.forEach((mediaInUsage) => {
            expect(mediaInUsage.fileName).toMatch(itemDeleteMock().fileName);
        });
    });

    it('should not return media in usage when it is a folder', async () => {
        const folderDeleteMock = {
            getEntityName: () => {
                return 'media_folder';
            },
            id: 'kc3m3iw0289d82392nd8cd33d3d3',
            name: 'folder test',
        };

        const wrapper = await createWrapper([folderDeleteMock]);
        expect(wrapper.vm.snippets.deleteMessage).toBe('global.sw-media-modal-delete.deleteMessage.folder');
        expect(wrapper.vm.mediaQuickInfo).toBeNull();
        expect(wrapper.vm.mediaInUsages).toHaveLength(0);
        expect(wrapper.find('.sw-media-quickinfo-usage').exists()).toBeFalsy();
        expect(wrapper.find('.sw-media-media-item').exists()).toBeFalsy();
    });

    it('should handle media without loaded usage associations', async () => {
        const wrapper = await createWrapper([
            {
                getEntityName: () => {
                    return 'media';
                },
                id: 'media-without-associations',
                fileName: 'fresh-upload',
                fileExtension: 'jpg',
            },
        ]);

        expect(wrapper.vm.mediaQuickInfo).toBeNull();
        expect(wrapper.vm.mediaInUsages).toHaveLength(0);
    });

    it('should use media filename fallback when delete notification cannot build a full media name', async () => {
        const deleteMock = jest.fn(() => Promise.reject(new Error('Delete failed')));
        const wrapper = await createWrapper(
            [
                itemDeleteMock({
                    fileName: 'demo.jpg',
                    fileExtension: undefined,
                }),
            ],
            deleteMock,
        );
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.deleteSelection();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Error deleting file "demo.jpg".',
        });
    });

    it('should truncate long media names in delete error notifications', async () => {
        const deleteMock = jest.fn(() => Promise.reject(new Error('Delete failed')));
        const wrapper = await createWrapper(
            [
                itemDeleteMock({
                    fileName: 'this-is-a-very-long-media-file-name-that-should-stay-readable-in-notifications',
                    fileExtension: 'jpg',
                }),
            ],
            deleteMock,
        );
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.deleteSelection();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Error deleting file "this-is-a-very-long-med...e-in-notifications.jpg".',
        });
    });

    it('should only emit successfully deleted items after partial delete failure', async () => {
        const successfulMedia = itemDeleteMock({
            id: 'successful-media-id',
            fileName: 'success',
            fileExtension: 'jpg',
        });
        const failedMedia = itemDeleteMock({
            id: 'failed-media-id',
            fileName: 'failed',
            fileExtension: 'jpg',
        });
        const deleteMock = jest.fn((id) => {
            if (id === failedMedia.id) {
                return Promise.reject(new Error('Delete failed'));
            }

            return Promise.resolve();
        });
        const wrapper = await createWrapper(
            [
                successfulMedia,
                failedMedia,
            ],
            deleteMock,
        );
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.deleteSelection();

        expect(wrapper.emitted('media-delete-modal-items-delete')[0][0]).toEqual({
            mediaIds: ['successful-media-id'],
            folderIds: [],
        });
    });

    it('should show successful deletions in a snackbar', async () => {
        const deleteMock = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper(
            [
                itemDeleteMock({
                    id: 'successful-media-id',
                    fileName: 'success',
                    fileExtension: 'jpg',
                }),
            ],
            deleteMock,
        );

        await wrapper.vm.deleteSelection();

        expect(mockSnackbar.addSnackbar).toHaveBeenCalledWith({
            variant: 'success',
            message: '1/1 files deleted.',
        });
    });
});
