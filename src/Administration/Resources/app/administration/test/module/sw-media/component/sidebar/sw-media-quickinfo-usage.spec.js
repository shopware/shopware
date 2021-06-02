import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-media/component/sidebar/sw-media-quickinfo-usage';

const { Module } = Shopware;
const ModuleFactory = Module;
const register = ModuleFactory.register;

describe('module/sw-media/components/sw-media-quickinfo-usage', () => {
    const itemDeleteMock = (options = {}) => {
        return {
            getEntityName: () => { return 'media'; },
            id: '4a12jd3kki9yyy765gkn5hdb',
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

    let wrapper;
    let moduleMock;
    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-media-quickinfo-usage'), {
            stubs: {
                'router-link': true,
                'sw-icon': true,
                'sw-alert': true
            },
            propsData: { item: itemDeleteMock() }
        });

        const modules = ModuleFactory.getModuleRegistry();
        modules.clear();

        moduleMock = {
            type: 'core',
            name: 'settings',
            routes: {
                index: {
                    component: 'sw-settings-index',
                    path: 'index',
                    icon: 'default-action-settings'
                }
            },
            manifest: {
                color: '#9AA8B5',
                icon: 'default-action-settings'
            }
        };
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be correct to show media in used information when user select a media', async () => {
        register('sw-product', moduleMock);
        const productMediaMock = {
            id: '98hhh7gh31d2d23dj292hjd7b',
            product: {
                translated: { name: 'name test' }
            }
        };

        await wrapper.setProps({ item: itemDeleteMock({ productMedia: [productMediaMock] }) });
        expect(wrapper.vm.getUsages.some(usage => usage.name === productMediaMock.product.translated.name)).toBeTruthy();
    });

    it('should be correct show all of media in used information', async () => {
        register('sw-settings-user', moduleMock);
        const avatarUserMock = { username: 'abc123' };

        register('sw-product', moduleMock);
        const productMediaMock = {
            product: { translated: { name: 'product test' } }
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
                translated: { description: 'mail test' }
            }
        };

        register('sw-settings-document', moduleMock);
        const documentBaseConfigMock = { name: 'document test' };

        register('sw-settings-payment', moduleMock);
        const paymentMock = { translated: { distinguishableName: 'payment test' } };

        register('sw-settings-shipping', moduleMock);
        const shippingMock = { translated: { name: 'shipping test' } };

        await wrapper.setProps({
            item: itemDeleteMock({
                avatarUser: avatarUserMock,
                productMedia: [productMediaMock],
                categories: [categoryMock],
                productManufacturers: [manufacturerMock],
                mailTemplateMedia: [mailTemplateMediaMock],
                documentBaseConfigs: [documentBaseConfigMock],
                paymentMethods: [paymentMock],
                shippingMethods: [shippingMock]
            })
        });

        const usages = wrapper.vm.getUsages;
        expect(usages.some((usage) => usage.name === avatarUserMock.username)).toBeTruthy();
        expect(usages.some((usage) => usage.name === productMediaMock.product.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === categoryMock.translated.name)).toBeTruthy();
        expect(usages.some((usage) => usage.name === manufacturerMock.translated.name)).toBeTruthy();
        expect(
            usages.some((usage) => usage.name === mailTemplateMediaMock.mailTemplate.translated.description)
        ).toBeTruthy();
        expect(usages.some((usage) => usage.name === paymentMock.translated.distinguishableName)).toBeTruthy();
        expect(usages.some((usage) => usage.name === shippingMock.translated.name)).toBeTruthy();
    });
});
