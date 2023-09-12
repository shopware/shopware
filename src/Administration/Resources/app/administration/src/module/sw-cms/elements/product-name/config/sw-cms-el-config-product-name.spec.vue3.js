/**
 * @package content
 */
import { mount } from '@vue/test-utils_v3';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

async function createWrapper(propsOverride) {
    return mount(await wrapTestComponent('sw-cms-el-config-product-name', {
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
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'product_detail',
                    },
                },
            };
        },
        global: {
            provide: {
                cmsService: {},
            },
            stubs: {
                'sw-tabs': {
                    data() {
                        return {
                            active: '',
                        };
                    },
                    template: `
                    <div class="sw-tabs">
                        <slot name="default" v-bind="{ active }"></slot>
                        <slot name="content" v-bind="{ active }"></slot>
                    </div>
                `,
                },
                'sw-container': true,
                'sw-tabs-item': true,
            },
        },
    });
}

describe('module/sw-cms/elements/product-name/config', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should map to a product name if the component is in a product page', async () => {
        expect(wrapper.vm.element.config.content.source).toBe('mapped');
        expect(wrapper.vm.element.config.content.value).toBe('product.name');
    });

    it('should not initially map to a product name if element translated config exists', async () => {
        wrapper = await createWrapper({
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

    it('should not initially map to a product name if element config exists', async () => {
        wrapper = await createWrapper({
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: 'Sample Product 1',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
                translated: {
                    config: null,
                },
            },
        });

        expect(wrapper.vm.element.config.content.source).toBe('static');
        expect(wrapper.vm.element.config.content.value).toBe('Sample Product 1');
    });
});
