/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-text', { sync: true }), {
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
            stubs: {
                'sw-text-editor': await wrapTestComponent('sw-text-editor'),
                'sw-text-editor-toolbar': true,
                'sw-text-editor-table-toolbar': true,
                'sw-code-editor': true,
                'sw-container': true,
                'sw-field-error': true,
            },
        },
        props: {
            element: {
                config: {
                    content: {
                        value: '',
                    },
                },
            },
        },
    });
}

describe('src/module/sw-cms/elements/text/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/text');
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('updates the demo value if demo entity changes', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'product.name',
                    },
                },
            },
        });

        wrapper.vm.updateDemoValue = jest.fn();

        await Shopware.Store.get('cmsPage').setCurrentDemoEntity({
            id: 'foo-bar',
        });
        expect(wrapper.vm.updateDemoValue).toHaveBeenCalled();
    });

    it('properly dispatches internal events', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onInput('foo');
        expect(wrapper.emitted()['element-update'][0][0]).toMatchObject(wrapper.vm.element);

        wrapper.vm.onBlur('bar');
        expect(wrapper.emitted()['element-update'][0][0]).toMatchObject(wrapper.vm.element);

        jest.clearAllMocks();
    });

    it('emitChanges early returns and does not emit if value equals current config', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'product.name',
                    },
                },
            },
        });

        wrapper.vm.emitChanges('product.name');
        expect(wrapper.emitted()['element-update']).toBeUndefined();
    });
});
