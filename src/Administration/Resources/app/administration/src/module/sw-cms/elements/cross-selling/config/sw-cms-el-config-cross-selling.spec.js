/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

async function createWrapper(customCmsElementConfig) {
    const productMock = {
        name: 'Small Silk Heart Worms',
    };

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
                'sw-modal': true,
                'sw-entity-single-select': true,
                'sw-alert': true,
                'sw-icon': true,
                'sw-product-variant-info': true,
                'sw-select-result': true,
                'sw-select-field': true,
                'sw-text-field': true,
            },
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return {};
                    },
                },
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
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPageState',

            state() {
                return {
                    currentPage: {
                        type: 'landingpage',
                    },
                };
            },
        });
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPageState').$reset();
    });

    it('should display a message if it is product page layout type', async () => {
        const wrapper = await createWrapper();

        const productSelect = wrapper.find('sw-entity-single-select-stub');

        expect(productSelect.exists()).toBe(true);
    });

    it('should display product select if it is not product page layout type', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('cmsPageState').currentPage.type = 'product_detail';

        await flushPromises();

        const alertMessage = wrapper.find('sw-alert-stub');

        expect(alertMessage.exists()).toBe(true);
        expect(alertMessage.text()).toBe('sw-cms.elements.crossSelling.config.infoText.productDetailElement');
    });
});
