/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import swCmsElText from 'src/module/sw-cms/elements/text/component';
import swCmsElProductName from 'src/module/sw-cms/elements/product-name/component';

Shopware.Component.register('sw-cms-el-text', swCmsElText);
Shopware.Component.extend('sw-cms-el-product-name', 'sw-cms-el-text', swCmsElProductName);

async function createWrapper(propsOverride) {
    return shallowMount(await Shopware.Component.build('sw-cms-el-product-name'), {
        propsData: {
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
        mocks: {
            $sanitize: key => key,
        },
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'product_detail',
                    },
                },
            };
        },
        provide: {
            cmsService: {
                getPropertyByMappingPath: () => {},
            },
        },
        stubs: {
            'sw-text-editor': true,
        },
    });
}

describe('module/sw-cms/elements/product-name/component', () => {
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
});
