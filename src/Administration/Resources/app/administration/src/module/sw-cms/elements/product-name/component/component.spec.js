/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper(propsOverride) {
    return mount(await wrapTestComponent('sw-cms-el-product-name', {
        sync: true,
    }), {
        props: {
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: null,
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
            },
            defaultConfig: {},
            ...propsOverride,
        },
        global: {
            mocks: {
                $sanitize: key => key,
            },
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
            stubs: {
                'sw-text-editor': true,
            },
        },
    });
}

describe('module/sw-cms/elements/product-name/component', () => {
    beforeAll(async () => {
        await import('src/module/sw-cms/state/cms-page.state');
        await import('src/module/sw-cms/service/cms.service');
        await import('src/module/sw-cms/mixin/sw-cms-element.mixin');
    });

    afterEach(() => {
        Shopware.Store.get('cmsPageState').resetCmsPageState();
    });

    it('should map to a product name if the component is in a product page', async () => {
        Shopware.Store.get('cmsPageState').setCurrentPage({
            type: 'product_detail',
        });
        const wrapper = await createWrapper();

        expect(wrapper.vm.element.config.content.source).toBe('mapped');
        expect(wrapper.vm.element.config.content.value).toBe('product.name');
    });

    it('should not initially map to a product name if element translated config exists', async () => {
        const wrapper = await createWrapper({
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: 'Sample Product',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
                translated: {
                    config: {
                        content: {
                            source: 'static',
                            value: 'Sample Product',
                        },
                    },
                },
            },
        });

        expect(wrapper.vm.element.config.content.source).toBe('static');
        expect(wrapper.vm.element.config.content.value).toBe('Sample Product');
    });

    it('should display skeleton on product name block if entity demo is null', async () => {
        Shopware.Store.get('cmsPageState').setCurrentPage({
            type: 'product_detail',
        });
        const wrapper = await createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentDemoEntity: null,
            },
        });

        expect(wrapper.find('.sw-cms-el-product-name__placeholder').exists()).toBeTruthy();
    });

    it('should display placeholder on product name block if data mapping is set to "product.name"', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'product.name',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
            },
        });

        await wrapper.setData({
            cmsPageState: {
                currentDemoEntity: null,
            },
        });

        expect(wrapper.find('.sw-cms-el-product-name__placeholder').exists()).toBeTruthy();
    });

    it('should display skeleton on product name block if data mapping is not set to "product.name"', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'product.ean',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
            },
        });

        await wrapper.setData({
            cmsPageState: {
                currentDemoEntity: null,
            },
        });

        expect(wrapper.find('.sw-cms-el-product-name__skeleton').exists()).toBeTruthy();
    });

    it('demoValue is retrieved from cms state, if it exists', async () => {
        Shopware.Store.get('cmsPageState').setCurrentDemoEntity({
            name: 'Test product',
            ean: 'test-ean',
        });

        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'product.ean',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
            },
        });

        expect(wrapper.vm.demoValue).toBe('test-ean');
    });
});
