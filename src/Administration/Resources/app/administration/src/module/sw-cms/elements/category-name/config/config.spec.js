/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(propsOverride) {
    return mount(
        await wrapTestComponent('sw-cms-el-config-category-name', {
            sync: true,
        }),
        {
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
                    cmsService: Shopware.Service('cmsService'),
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
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item', {
                        sync: true,
                    }),
                    'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field', { sync: true }),
                    'sw-text-editor': {
                        props: ['value'],
                        emits: [
                            'blur',
                            'update:value',
                            'change',
                        ],
                        template:
                            '<input type="text" :value="value" @blur="$emit(\'blur\', $event.target.value)" @input="$emit(\'update:value\', $event.target.value)" @change="$emit(\'change\', $event.target.value)"></input>',
                    },
                    'sw-select-field': true,
                    'sw-extension-component-section': true,
                    'router-link': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-cms-inherit-wrapper': {
                        template: '<div><slot :isInherited="false"></slot></div>',
                        props: [
                            'field',
                            'element',
                            'contentEntity',
                            'label',
                        ],
                    },
                },
            },
        },
    );
}

describe('module/sw-cms/elements/category-name/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_list',
        });
    });

    it('maps to category.name when used on a category detail page', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.element.config.content.source).toBe('mapped');
        expect(wrapper.vm.element.config.content.value).toBe('category.name');
    });

    it('keeps an existing translated config without overwriting', async () => {
        const wrapper = await createWrapper({
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: 'Sample Category',
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
                            value: 'Sample Category',
                        },
                    },
                },
            },
        });

        expect(wrapper.vm.element.config.content.source).toBe('static');
        expect(wrapper.vm.element.config.content.value).toBe('Sample Category');
    });

    it('keeps an existing non-translated config', async () => {
        const wrapper = await createWrapper({
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: 'Sample Category 1',
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
        expect(wrapper.vm.element.config.content.value).toBe('Sample Category 1');
    });
});
