/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';
import { MtTextEditor } from '@shopware-ag/meteor-component-library';

// Define the mock htmlToJsonService
const mockHtmlToJsonService = {
    transform: jest.fn(content => ({
        MOCKED_SCHEMA: true,
        originalContent: content,
    })),
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-text', { sync: true }), {
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
                // Provide the mock for components that might inject it
                htmlToJsonService: mockHtmlToJsonService,
            },
            stubs: {
                'mt-text-editor': MtTextEditor,
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
        // Register the mock service globally AFTER setupCmsEnvironment
        Shopware.Service().register('htmlToJsonService', () => mockHtmlToJsonService);
        await import('src/module/sw-cms/elements/text');
    });

    beforeEach(() => {
        // Clear mock history before each test
        mockHtmlToJsonService.transform.mockClear();
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
        // Component should use the globally registered mockHtmlToJsonService

        wrapper.vm.onInput('foo');
        expect(mockHtmlToJsonService.transform).toHaveBeenCalledWith('foo');
        expect(wrapper.emitted()['element-update'][0][0]).toMatchObject(wrapper.vm.element);

        wrapper.vm.onBlur('bar');
        expect(mockHtmlToJsonService.transform).toHaveBeenCalledWith('bar');
        // Correctly check the second emitted event for onBlur
        expect(wrapper.emitted()['element-update'][1][0]).toMatchObject(wrapper.vm.element);
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

    // Test that htmlToJsonService.transform is called correctly on input
    it('calls htmlToJsonService.transform on input', async () => {
        const wrapper = await createWrapper();

        // Initial value is '', so 'foo' is a change.
        wrapper.vm.onInput('foo');

        // Check that the transform method of our mock was called
        expect(mockHtmlToJsonService.transform).toHaveBeenCalledWith('foo');
    });

    // Test that htmlToJsonService.transform is called correctly on blur
    it('calls htmlToJsonService.transform on blur', async () => {
        const wrapper = await createWrapper();

        // Initial value is '', so 'bar' is a change.
        wrapper.vm.onBlur('bar');

        // Check that the transform method of our mock was called
        expect(mockHtmlToJsonService.transform).toHaveBeenCalledWith('bar');
    });
});
