/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const productMock = {
    id: 'foo-bar',
    name: 'Small Silk Heart Worms',
};

async function createWrapper(customCmsElementConfig) {
    return mount(await wrapTestComponent('sw-cms-el-config-cross-selling', {
        sync: true,
    }), {
        props: {
            element: {
                config: {
                    title: {
                        value: '',
                    },
                    product: {
                        value: 'de8de156da134dabac24257f81ff282f',
                        source: 'static',
                    },
                    ...customCmsElementConfig,
                },
                data: {},
            },
            defaultConfig: {},
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
                },
                'sw-tabs-item': true,
                'sw-container': true,
                'sw-field': true,
                'sw-text-field': true,
                'sw-select-field': true,
                'sw-select-result': true,
                'sw-modal': true,
                'sw-entity-single-select': true,
                'sw-product-variant-info': true,
                'sw-alert': true,
                'sw-icon': true,
            },
            provide: {
                cmsService: Shopware.Service('cmsService'),
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(productMock),
                            search: () => Promise.resolve(productMock),
                        };
                    },
                },
            },
        },
    });
}

describe('module/sw-cms/elements/cross-selling/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('should display a message if it is product page layout type', async () => {
        const wrapper = await createWrapper();

        const productSelect = wrapper.find('sw-entity-single-select-stub');

        expect(productSelect.exists()).toBe(true);
    });

    it('should display product select if it is product page layout type', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_detail',
        });
        const wrapper = await createWrapper();

        expect(wrapper.get('sw-alert-stub').text()).toBe('sw-cms.elements.crossSelling.config.infoText.productDetailElement');
    });

    it('onProductChange clears the product if no id provided', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onProductChange(null);

        expect(wrapper.vm.element.config.product.value).toBeNull();
        expect(wrapper.vm.element.data.product).toBeNull();
    });

    it('onProductChange queries the product if id provided', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onProductChange(productMock.id);

        expect(wrapper.vm.element.config.product.value).toBe(productMock.id);
        expect(wrapper.vm.element.data.product).toMatchObject(productMock);
    });
});
