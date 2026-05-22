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

const createWrapper = async (repositoryFactoryMock) => {
    return mount(await wrapTestComponent('sw-media-quickinfo-usage', { sync: true }), {
        props: { item: itemDeleteMock() },
        global: {
            stubs: {
                'router-link': true,
                'sw-media-collapse': {
                    props: ['title'],
                    template: `
                        <div class="sw-media-collapse-stub">
                            <h4>{{ title }}</h4>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-loader': true,
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
            product: {
                translated: { name: 'name test' },
            },
        };

        await wrapper.setProps({
            item: itemDeleteMock({ productMedia: [productMediaMock] }),
        });
        const productUsage = wrapper.vm.getUsages.find((usage) => usage.name === productMediaMock.product.translated.name);

        expect(productUsage).toBeTruthy();
        expect(productUsage.typeLabel).toBe('sw-media.sidebar.usage.tooltipFoundInProducts');
        expect(wrapper.vm.usageAriaLabel(productUsage)).toBe(
            'name test, sw-media.sidebar.usage.tooltipFoundInProducts',
        );
    });

    it('should hide used in section when media is not used', async () => {
        await flushPromises();

        expect(wrapper.vm.getUsages).toHaveLength(0);
        expect(wrapper.find('.sw-media-collapse-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-media-quickinfo-usage__info-not-used').exists()).toBe(false);
    });

    it('should show used in section when media is used', async () => {
        register('sw-product', moduleMock);
        const productMediaMock = {
            id: '98hhh7gh31d2d23dj292hjd7b',
            product: {
                translated: { name: 'name test' },
            },
        };

        await wrapper.setProps({
            item: itemDeleteMock({ productMedia: [productMediaMock] }),
        });

        expect(wrapper.find('.sw-media-collapse-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-media-collapse-stub').text()).toContain('sw-media.sidebar.sections.usage');
    });

    it('should be correct show all of media in used information', async () => {
        await wrapper.unmount();

        wrapper = await createWrapper({
            create: (entityName) => {
                return {
                    search: () => {
                        if (entityName === 'product') {
                            return Promise.resolve([
                                { id: 'a', translated: { name: 'Product Media Test' } },
                            ]);
                        }

                        if (entityName === 'category') {
                            return Promise.resolve([
                                { id: 'b', translated: { name: 'Category Media Test' } },
                            ]);
                        }

                        if (entityName === 'landing_page') {
                            return Promise.resolve([
                                { id: 'c', translated: { name: 'Landing Page Media Test' } },
                            ]);
                        }

                        if (entityName === 'cms_page') {
                            return Promise.resolve([
                                { id: 'd', name: 'CMS Page Media Test' },
                            ]);
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
            product: { translated: { name: 'product test' } },
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
});
