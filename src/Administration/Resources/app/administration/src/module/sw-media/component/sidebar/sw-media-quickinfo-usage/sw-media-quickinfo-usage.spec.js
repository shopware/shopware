/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const { Module } = Shopware;
const ModuleFactory = Module;
const register = ModuleFactory.register;

const itemDeleteMock = (options = {}) => {
    return {
        getEntityName: () => {
            return 'media';
        },
        id: '4a12jd3kki9yyy765gkn5hdb',
        fileName: 'demo.jpg',
        avatarUsers: [],
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

const allUsageReads = [
    'product_media:read',
    'product:read',
    'product_document:read',
    'product_document_translation:read',
    'category:read',
    'landing_page:read',
    'product_manufacturer:read',
    'mail_template_media:read',
    'mail_template:read',
    'document_base_config:read',
    'user:read',
    'payment_method:read',
    'shipping_method:read',
    'cms_page:read',
    'cms_section:read',
    'cms_block:read',
    'cms_slot:read',
];

const createWrapper = async (repositoryFactoryMock) => {
    return mount(await wrapTestComponent('sw-media-quickinfo-usage', { sync: true }), {
        props: { item: itemDeleteMock() },
        global: {
            stubs: {
                'router-link': true,
                'mt-loader': true,
                'sw-unchecked-areas-hint': await wrapTestComponent('sw-unchecked-areas-hint', { sync: true }),
            },
            mocks: {
                $t: (key, params) => (params ? `${key} ${Object.values(params).join(' ')}` : key),
            },
            provide: {
                repositoryFactory: repositoryFactoryMock ?? {
                    create: () => {
                        return {
                            search: () => {
                                return Promise.resolve([]);
                            },
                        };
                    },
                },
            },
        },
    });
};

describe('module/sw-media/components/sw-media-quickinfo-usage', () => {
    let wrapper;
    let moduleMock;
    beforeEach(async () => {
        global.activeAclRoles = [...allUsageReads];
        wrapper = await createWrapper();

        const modules = ModuleFactory.getModuleRegistry();
        modules.clear();

        moduleMock = {
            type: 'core',
            name: 'settings',
            routes: {
                index: {
                    component: 'sw-settings-index',
                    path: 'index',
                    icon: 'default-action-settings',
                },
            },
            manifest: {
                color: '#9AA8B5',
                icon: 'default-action-settings',
            },
        };
    });

    it('should be correct to show media in used information when user select a media', async () => {
        register('sw-product', moduleMock);
        const productMediaMock = {
            id: '98hhh7gh31d2d23dj292hjd7b',
            productId: 'product-media',
            product: {
                id: 'product-media',
                translated: { name: 'name test' },
            },
        };

        await wrapper.setProps({
            item: itemDeleteMock({ productMedia: [productMediaMock] }),
        });
        expect(wrapper.vm.getUsages.some((usage) => usage.name === productMediaMock.product.translated.name)).toBeTruthy();
    });

    it('should be correct show all of media in used information', async () => {
        await wrapper.unmount();

        wrapper = await createWrapper({
            create: (entityName) => {
                return {
                    search: () => {
                        if (entityName === 'product') {
                            return Promise.resolve([{ id: 'a', translated: { name: 'Product Media Test' } }]);
                        }

                        if (entityName === 'category') {
                            return Promise.resolve([{ id: 'b', translated: { name: 'Category Media Test' } }]);
                        }

                        if (entityName === 'landing_page') {
                            return Promise.resolve([{ id: 'c', translated: { name: 'Landing Page Media Test' } }]);
                        }

                        if (entityName === 'cms_page') {
                            return Promise.resolve([{ id: 'd', name: 'CMS Page Media Test' }]);
                        }

                        return Promise.resolve([]);
                    },
                };
            },
        });

        register('sw-users-permissions', moduleMock);
        const avatarUserMock = { username: 'abc123' };

        register('sw-product', moduleMock);
        const productMediaMock = {
            productId: 'product-media',
            product: { id: 'product-media', translated: { name: 'product test' } },
        };

        register('sw-category', moduleMock);
        const categoryMock = { translated: { name: 'category test' } };

        register('sw-manufacturer', moduleMock);
        const manufacturerMock = { translated: { name: 'manufacturer test' } };

        register('sw-mail-template', moduleMock);
        const mailTemplateMediaMock = {
            id: '8u7bb3kn5hx82jd01jk1sdc',
            mailTemplate: {
                id: 'k8j7hh6gc5v66fr3rdd222da',
                translated: { description: 'mail test' },
            },
        };

        register('sw-settings-document', moduleMock);
        const documentBaseConfigMock = { name: 'document test' };

        register('sw-settings-payment', moduleMock);
        const paymentMock = {
            translated: { distinguishableName: 'payment test' },
        };

        register('sw-settings-shipping', moduleMock);
        const shippingMock = { translated: { name: 'shipping test' } };

        register('sw-cms', moduleMock);
        const cmsBlockMock = {
            section: {
                pageId: 'cmsBlockId',
                page: { translated: { name: 'cms block test' } },
            },
        };
        const cmsSectionMock = {
            pageId: 'cmsSectionId',
            page: { translated: { name: 'cms section test' } },
        };
        const cmsPageMock = {
            id: 'cmsPageId',
            translated: { name: 'cms page test' },
        };

        await wrapper.setProps({
            item: itemDeleteMock({
                avatarUsers: [avatarUserMock],
                productMedia: [productMediaMock],
                categories: [categoryMock],
                productManufacturers: [manufacturerMock],
                mailTemplateMedia: [mailTemplateMediaMock],
                documentBaseConfigs: [documentBaseConfigMock],
                paymentMethods: [paymentMock],
                shippingMethods: [shippingMock],
                cmsBlocks: [cmsBlockMock],
                cmsSections: [cmsSectionMock],
                cmsPages: [cmsPageMock],
            }),
        });
        await wrapper.vm.loadSlotConfigAssociations();

        const usages = wrapper.vm.getUsages;
        expect(usages.some((usage) => usage.name === avatarUserMock.username)).toBeTruthy();
        expect(usages.some((usage) => usage.name === productMediaMock.product.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === categoryMock.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === manufacturerMock.translated.name)).toBeTruthy();
        expect(
            usages.some((usage) => usage.name === mailTemplateMediaMock.mailTemplate.translated.description),
        ).toBeTruthy();
        expect(usages.some((usage) => usage.name === paymentMock.translated.distinguishableName)).toBeTruthy();
        expect(usages.some((usage) => usage.name === shippingMock.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === cmsBlockMock.section.page.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === cmsSectionMock.page.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === cmsPageMock.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === documentBaseConfigMock.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === 'Product Media Test')).toBeTruthy();
        expect(usages.some((usage) => usage.name === 'Category Media Test')).toBeTruthy();
        expect(usages.some((usage) => usage.name === 'Landing Page Media Test')).toBeTruthy();
        expect(usages.some((usage) => usage.name === 'CMS Page Media Test')).toBeTruthy();
    });

    it('must not mutate the media entity productMedia / categories collections when loading slot-config usages', async () => {
        await wrapper.unmount();

        wrapper = await createWrapper({
            create: (entityName) => {
                return {
                    search: () => {
                        if (entityName === 'product') {
                            return Promise.resolve([{ id: 'slot-product-id', translated: { name: 'Slot Product' } }]);
                        }

                        if (entityName === 'category') {
                            return Promise.resolve([{ id: 'slot-category-id', translated: { name: 'Slot Category' } }]);
                        }

                        return Promise.resolve([]);
                    },
                };
            },
        });

        register('sw-product', moduleMock);
        register('sw-category', moduleMock);

        const item = itemDeleteMock();
        const originalProductMedia = item.productMedia;
        const originalCategories = item.categories;

        await wrapper.setProps({ item });
        await wrapper.vm.loadSlotConfigAssociations();

        expect(item.productMedia).toBe(originalProductMedia);
        expect(item.productMedia).toHaveLength(0);
        expect(item.categories).toBe(originalCategories);
        expect(item.categories).toHaveLength(0);

        const usages = wrapper.vm.getUsages;
        expect(usages.some((usage) => usage.name === 'Slot Product')).toBeTruthy();
        expect(usages.some((usage) => usage.name === 'Slot Category')).toBeTruthy();
    });

    it('only searches the areas the user may read', async () => {
        await wrapper.unmount();
        global.activeAclRoles = [
            'category:read',
            'cms_page:read',
        ];

        const searchedEntities = [];
        wrapper = await createWrapper({
            create: (entityName) => {
                return {
                    search: () => {
                        searchedEntities.push(entityName);

                        return Promise.resolve([]);
                    },
                };
            },
        });
        await flushPromises();

        expect(searchedEntities).toEqual(['category']);
        expect(wrapper.find('mt-loader-stub').exists()).toBe(false);
    });

    it('names the areas it did not check and adapts the empty state', async () => {
        await wrapper.unmount();
        global.activeAclRoles = [
            'product_media:read',
            'product:read',
            'product_document:read',
            'product_document_translation:read',
            'category:read',
        ];

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-media-quickinfo-usage__info-not-used').text()).toContain(
            'sw-media.sidebar.usage.labelMediaNotUsedInReadableAreas',
        );

        const hint = wrapper.find('.sw-media-quickinfo-usage__unchecked-areas');
        expect(hint.text()).toContain('global.sw-unchecked-areas-hint.text');
        expect(hint.text()).toContain(
            [
                'sw-media.sidebar.usage.areaLandingPages',
                'sw-media.sidebar.usage.areaManufacturers',
                'sw-media.sidebar.usage.areaMailTemplates',
                'sw-media.sidebar.usage.areaDocuments',
                'sw-media.sidebar.usage.areaUsers',
                'sw-media.sidebar.usage.areaPaymentMethods',
                'sw-media.sidebar.usage.areaShippingMethods',
                'sw-media.sidebar.usage.areaLayouts',
            ].join(', '),
        );
        expect(hint.text()).not.toContain('areaProducts');
        expect(hint.text()).not.toContain('areaCategories');
    });

    it('shows no hint when the user may read every area', async () => {
        await flushPromises();

        const emptyState = wrapper.find('.sw-media-quickinfo-usage__info-not-used').text();
        expect(emptyState).toContain('sw-media.sidebar.usage.labelMediaNotUsed');
        expect(emptyState).not.toContain('InReadableAreas');
        expect(wrapper.find('.sw-media-quickinfo-usage__unchecked-areas').exists()).toBe(false);
    });
});
