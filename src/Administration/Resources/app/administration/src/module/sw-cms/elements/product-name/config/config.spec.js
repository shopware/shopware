/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

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
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item', { sync: true }),
                'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field', { sync: true }),
                'sw-text-editor': {
                    props: ['value'],
                    emits: ['blur', 'update:value', 'change'],
                    template: '<input type="text" :value="value" @blur="$emit(\'blur\', $event.target.value)" @input="$emit(\'update:value\', $event.target.value)" @change="$emit(\'change\', $event.target.value)"></input>',
                },
                'sw-select-field': true,
                'sw-icon': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'sw-alert': true,
            },
        },
    });
}

describe('module/sw-cms/elements/product-name/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPageState').resetCmsPageState();
        Shopware.Store.get('cmsPageState').setCurrentPage({
            type: 'product_detail',
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

    it('should not initially map to a product name if element config exists', async () => {
        const wrapper = await createWrapper({
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
