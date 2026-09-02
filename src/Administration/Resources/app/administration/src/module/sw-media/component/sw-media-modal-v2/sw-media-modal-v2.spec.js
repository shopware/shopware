/**
 * @sw-package discovery
 */
import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

async function createWrapper({ featureActive = false } = {}) {
    return mount(await wrapTestComponent('sw-media-modal-v2', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'mt-modal': true,
                'mt-modal-root': true,
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: ['new-item-active'],
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                    template: '<div class="mt-tabs"></div>',
                },
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot name="content" active="upload"></slot></div>',
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
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
                repositoryFactory: {},
                mediaService: {},
            },
        },
    });
}

describe('src/module/sw-media/component/sw-media-modal-v2', () => {
    let wrapper;

    beforeEach(async () => {
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

    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs with the expected item API when the major feature flag is active', async () => {
        wrapper = await createWrapper({ featureActive: true });

        const tabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(tabs.exists()).toBe(true);
        expect(tabs.props('positionIdentifier')).toBe('sw-media-modal');
        expect(tabs.props('defaultItem')).toBe('library');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-media.sw-media-modal-v2.labelTabItemLibrary',
                name: 'library',
                disabled: false,
            },
            {
                label: 'sw-media.sw-media-modal-v2.labelTabItemUpload',
                name: 'upload',
                onClick: expect.any(Function),
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
    });

    it('should switch active content when meteor tabs emit a new active item', async () => {
        wrapper = await createWrapper({ featureActive: true });

        const tabs = wrapper.findComponent({ name: 'mt-tabs' });

        wrapper.vm.selection = [{ id: 'selected-media' }];
        await nextTick();
        await tabs.vm.$emit('new-item-active', 'upload');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('upload');
        expect(wrapper.vm.selection).toEqual([]);
        expect(wrapper.find('.sw-media-modal-v2__library-content').attributes('style')).toBe('display: none;');
        expect(wrapper.find('.sw-media-modal-v2__uploads-content').attributes('style')).not.toBe('display: none;');
    });

    it('should disable the library item in meteor tabs when uploads exist', async () => {
        wrapper = await createWrapper({ featureActive: true });

        wrapper.vm.uploads = [{ id: 'uploaded-media' }];
        await nextTick();

        expect(wrapper.vm.mediaModalTabs[0].disabled).toBe(true);
    });
});
