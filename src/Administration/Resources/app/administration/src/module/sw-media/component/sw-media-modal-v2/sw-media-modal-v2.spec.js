/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-media-modal-v2', { sync: true }), {
        props: {
            uploadTag: 'my-upload',
            ...props,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'mt-modal': true,
                'mt-modal-root': true,
                'sw-tabs': {
                    template: '<div><slot name="content" active="upload"></slot></div>',
                },
                'mt-tabs': {
                    props: {
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: false,
                            default: '',
                        },
                        defaultItem: {
                            type: String,
                            required: false,
                            default: '',
                        },
                        variant: {
                            type: String,
                            required: false,
                            default: '',
                        },
                    },
                    emits: ['new-item-active'],
                    template: '<div class="mt-tabs-stub"></div>',
                },
                'sw-media-sidebar': true,
                'sw-media-upload-v2': true,
                'sw-upload-listener': true,
                'sw-media-grid': true,
                'sw-tabs-item': true,
                'sw-media-breadcrumbs': true,
                'sw-simple-search-field': true,
                'sw-media-library': true,
                'sw-media-media-item': true,
            },
            provide: {
                repositoryFactory: {},
                mediaService: {},
            },
        },
    });
}

describe('src/module/sw-media/component/sw-media-modal-v2', () => {
    let wrapper;

    beforeEach(async () => {
        global.activeFeatureFlags = [];

        wrapper = await createWrapper();
    });

    it('should contain the default accept value', async () => {
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('image/*');
    });

    it('should contain "application/pdf" value', async () => {
        await wrapper.setProps({
            fileAccept: 'application/pdf',
        });
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('application/pdf');
    });

    it('should render Meteor tabs when the feature is active', async () => {
        await wrapper.unmount();

        global.activeFeatureFlags = ['V6_8_0_0'];
        wrapper = await createWrapper({ defaultTab: 'upload' });

        const mtTabs = wrapper.getComponent('.mt-tabs-stub');

        expect(mtTabs.props('positionIdentifier')).toBe('sw-media-modal');
        expect(mtTabs.props('variant')).toBe('minimal');
        expect(mtTabs.props('defaultItem')).toBe('upload');
        expect(mtTabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-media.sw-media-modal-v2.labelTabItemLibrary',
                name: 'library',
                disabled: false,
            }),
            expect.objectContaining({
                label: 'sw-media.sw-media-modal-v2.labelTabItemUpload',
                name: 'upload',
            }),
        ]);

        await wrapper.setData({
            selection: [{ id: 'media-item' }],
        });

        mtTabs.vm.$emit('new-item-active', 'upload');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('upload');
        expect(wrapper.vm.selection).toEqual([]);
    });
});
