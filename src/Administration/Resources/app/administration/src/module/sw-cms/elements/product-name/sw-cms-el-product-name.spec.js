/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

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
                cmsService: {
                    getPropertyByMappingPath: () => {},
                },
            },
            stubs: {
                'sw-text-editor': true,
            },
        },
    });
}

describe('module/sw-cms/elements/product-name/component', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => ({
                currentPage: {
                    type: 'product_detail',
                },
                currentDemoEntity: undefined,
            }),
        });
    });

    it('should map to a product name if the component is in a product page', async () => {
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
        const wrapper = await createWrapper();

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

        Shopware.Store.get('cmsPageState').currentDemoEntity = null;
        await flushPromises();

        expect(wrapper.find('.sw-cms-el-product-name__skeleton').exists()).toBeTruthy();
    });
});
