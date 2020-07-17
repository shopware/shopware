import { shallowMount } from '@vue/test-utils';
import 'src/app/component/media/sw-media-modal-delete';

describe('components/media/sw-media-modal-delete', () => {
    const itemDeleteMock = (options = {}) => {
        return {
            getEntityName: () => { return 'media'; },
            id: 'z928dj393n83o02092dh292hjd92',
            fileName: 'demo.jpg',
            avatarUser: {},
            categories: [],
            productManufacturers: [],
            productMedia: [],
            mailTemplateMedia: [],
            documentBaseConfigs: [],
            paymentMethods: [],
            shippingMethods: [],
            ...options
        };
    };

    const CreateWrapper = (itemDeleteOptions = null) => {
        const itemsToDelete = itemDeleteOptions || [itemDeleteMock()];

        return shallowMount(Shopware.Component.build('sw-media-modal-delete'), {
            stubs: {
                'sw-modal': true,
                'sw-button': true,
                'sw-media-quickinfo-usage': '<div class="sw-media-quickinfo-usage"></div>',
                'sw-media-media-item': '<div class="sw-media-media-item"></div>',
                'sw-alert': true
            },
            mocks: {
                $tc: key => key,
                $sanitize: key => key
            },
            provide: {
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve() })
                }
            },
            propsData: { itemsToDelete }
        });
    };

    it('should be a Vue.js component', () => {
        const wrapper = CreateWrapper();
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should return list of Media with only one media selected', () => {
        const productMediaMock = {
            id: '3093dl23hf83jh29d0jsj',
            product: {
                id: '1d20d83hjd1lsndoso9shj0',
                description: 'Sint illo iste ipsum. ',
                name: 'Incredible Marble Clean Music'
            }
        };

        const wrapper = CreateWrapper([
            itemDeleteMock({ productMedia: [productMediaMock] })
        ]);

        expect(wrapper.vm.mediaQuickInfo.fileName).toMatch(itemDeleteMock().fileName);
        expect(wrapper.vm.mediaQuickInfo.productMedia).toEqual([productMediaMock]);
        expect(wrapper.vm.mediaInUsages.length).toEqual(0);
        expect(wrapper.find('.sw-media-quickinfo-usage').exists()).toBeTruthy();
        expect(wrapper.find('.sw-media-media-item').exists()).toBeFalsy();
    });

    it('should return list of Media quick info usage with all of places already linked', () => {
        const avatarUser = { id: '2d221dd5z2d2d90389fj1d21' };
        const category = { id: '30f832hdk5bn383h23023hf02' };
        const productManufacturer = { id: '923hf9202jd02j29d72h20' };
        const mailTemplateMedia = { id: '292hf92h283f89303h20210f' };
        const documentBaseConfig = { id: '94hf02hnf02hf82292hf0202f' };
        const paymentMethod = { id: '02j2j0f02h2f0283nhf834h239f2' };
        const shippingMethod = { id: '02jhf92jf784jflsnhffi9989' };
        const productMedia = { id: 'f83hf3dn2k5nv83020283jf9320' };

        const wrapper = CreateWrapper([
            itemDeleteMock({
                id: '2028dh992hd021jdj0202j',
                avatarUser,
                categories: [category],
                productManufacturers: [productManufacturer],
                productMedia: [productMedia],
                mailTemplateMedia: [mailTemplateMedia],
                documentBaseConfigs: [documentBaseConfig],
                paymentMethods: [paymentMethod],
                shippingMethods: [shippingMethod]
            })
        ]);

        expect(wrapper.vm.mediaQuickInfo.fileName).toMatch(itemDeleteMock().fileName);
        expect(wrapper.vm.mediaInUsages.length).toEqual(0);
        expect(wrapper.vm.mediaQuickInfo.avatarUser).toEqual(avatarUser);
        expect(wrapper.vm.mediaQuickInfo.categories).toEqual([category]);
        expect(wrapper.vm.mediaQuickInfo.productManufacturers).toEqual([productManufacturer]);
        expect(wrapper.vm.mediaQuickInfo.productMedia).toEqual([productMedia]);
        expect(wrapper.vm.mediaQuickInfo.mailTemplateMedia).toEqual([mailTemplateMedia]);
        expect(wrapper.vm.mediaQuickInfo.documentBaseConfigs).toEqual([documentBaseConfig]);
        expect(wrapper.vm.mediaQuickInfo.paymentMethods).toEqual([paymentMethod]);
        expect(wrapper.vm.mediaQuickInfo.shippingMethods).toEqual([shippingMethod]);
        expect(wrapper.find('.sw-media-quickinfo-usage').exists()).toBeTruthy();
        expect(wrapper.find('.sw-media-media-item').exists()).toBeFalsy();
    });

    it('should return list of File have media in usage with more than one media selected', () => {
        const productMediaMock = {
            id: '02djd92jdj1d928djc93333nf90',
            product: {
                id: 'ds239djd29fj39fj30243d2d222d',
                description: 'Sint illo iste ipsum. ',
                name: 'Incredible Marble Clean Music'
            }
        };

        const manyMediaItemMock = [
            itemDeleteMock({ id: '28dh2xi3jw455g5sd331d', productMedia: [productMediaMock] }),
            itemDeleteMock({ id: 'ff32ff112d3t4gf2g44rd', productMedia: [productMediaMock] })
        ];

        const wrapper = CreateWrapper(manyMediaItemMock);
        expect(wrapper.vm.mediaInUsages.length).toEqual(manyMediaItemMock.length);
        wrapper.vm.mediaInUsages.forEach((mediaInUsage) => {
            expect(mediaInUsage.fileName).toMatch(itemDeleteMock().fileName);
        });
    });

    it('should not return media in usage when it is a folder', () => {
        const folderDeleteMock = {
            getEntityName: () => { return 'media_folder'; },
            id: 'kc3m3iw0289d82392nd8cd33d3d3',
            name: 'folder test'
        };

        const wrapper = CreateWrapper([folderDeleteMock]);
        expect(wrapper.vm.snippets.deleteMessage).toEqual('global.sw-media-modal-delete.deleteMessage.folder');
        expect(wrapper.vm.mediaQuickInfo).toEqual(null);
        expect(wrapper.vm.mediaInUsages.length).toEqual(0);
        expect(wrapper.find('.sw-media-quickinfo-usage').exists()).toBeFalsy();
        expect(wrapper.find('.sw-media-media-item').exists()).toBeFalsy();
    });
});
