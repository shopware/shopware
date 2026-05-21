/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(propsOverride) {
    return mount(
        await wrapTestComponent('sw-cms-el-category-name', {
            sync: true,
        }),
        {
            props: {
                element: {
                    config: {
                        content: {
                            source: 'static',
                            value: '',
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
                    $sanitize: (key) => key,
                },
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
                stubs: {
                    'sw-text-editor': true,
                },
            },
        },
    );
}

describe('module/sw-cms/elements/category-name/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('maps to category.name when used on a category detail page', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_list',
        });
        const wrapper = await createWrapper();

        expect(wrapper.vm.element.config.content.source).toBe('mapped');
        expect(wrapper.vm.element.config.content.value).toBe('category.name');
    });

    it('does not overwrite an existing translated config', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_list',
        });
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
                        verticalAlign: {
                            source: 'static',
                            value: null,
                        },
                    },
                },
            },
        });

        expect(wrapper.vm.element.config.content.source).toBe('static');
        expect(wrapper.vm.element.config.content.value).toBe('Sample Category');
    });

    it('shows a placeholder when mapped to category.name and no demo entity is loaded', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_list',
        });
        const wrapper = await createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentDemoEntity: null,
            },
        });

        expect(wrapper.find('.sw-cms-el-category-name__placeholder').exists()).toBeTruthy();
    });

    it('renders the resolved category name when a demo entity is present', async () => {
        Shopware.Store.get('cmsPage').setCurrentDemoEntity({
            name: 'Newsletter',
        });

        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'category.name',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
            },
        });

        expect(wrapper.vm.demoValue).toBe('<h1>Newsletter</h1>');
    });
});
