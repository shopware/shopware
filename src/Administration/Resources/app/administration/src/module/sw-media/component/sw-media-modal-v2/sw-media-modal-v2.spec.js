/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper({ featureActive = false, props = {} } = {}) {
    return mount(await wrapTestComponent('sw-media-modal-v2', { sync: true }), {
        props,
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
                'sw-media-sidebar': {
                    props: ['items'],
                    emits: ['media-item-selection-clear'],
                    template: '<sw-media-sidebar-stub />',
                },
                'sw-media-upload-v2': true,
                'sw-upload-listener': true,
                'sw-media-grid': true,
                'sw-tabs-item': true,
                'sw-media-breadcrumbs': true,
                'sw-simple-search-field': true,
                'sw-media-library': true,
                'sw-media-media-item': true,
                'sw-iframe-renderer': true,
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

        await wrapper.setData({
            selection: [{ id: 'selected-media' }],
        });
        await tabs.vm.$emit('new-item-active', 'upload');
        await flushPromises();

        expect(tabs.props('defaultItem')).toBe('upload');
        expect(wrapper.findComponent('sw-media-sidebar-stub').props('items')).toEqual([]);
        expect(wrapper.find('.sw-media-modal-v2__library-content').attributes('style')).toBe('display: none;');
        expect(wrapper.find('.sw-media-modal-v2__uploads-content').attributes('style')).not.toBe('display: none;');
    });

    it('should clear the selection when the sidebar emits media-item-selection-clear', async () => {
        await wrapper.setData({
            selection: [{ id: 'first-media' }, { id: 'second-media' }],
        });

        const sidebar = wrapper.findComponent('sw-media-sidebar-stub');
        expect(sidebar.props('items')).toHaveLength(2);
        await sidebar.vm.$emit('media-item-selection-clear');

        expect(sidebar.props('items')).toEqual([]);
    });

    it('should disable the library item in meteor tabs when uploads exist', async () => {
        wrapper = await createWrapper({ featureActive: true });

        await wrapper.setData({
            uploads: [{ id: 'uploaded-media' }],
        });

        expect(wrapper.findComponent({ name: 'mt-tabs' }).props('items')[0].disabled).toBe(true);
    });

    it('should add the app tabs after the built-in meteor tabs', async () => {
        wrapper = await createWrapper({
            featureActive: true,
            props: {
                extensionTabs: [{ label: 'Primitives', locationId: 'primitives-tab' }],
                extensionBaseUrl: 'https://app.example',
            },
        });

        expect(wrapper.findComponent({ name: 'mt-tabs' }).props('items')[2]).toEqual({
            label: 'Primitives',
            name: 'extension-primitives-tab',
        });
        expect(wrapper.find('sw-iframe-renderer-stub').exists()).toBe(false);
    });

    it('should render the location of an active app tab and clear the selection', async () => {
        wrapper = await createWrapper({
            featureActive: true,
            props: {
                extensionTabs: [{ label: 'Primitives', locationId: 'primitives-tab' }],
                extensionBaseUrl: 'https://app.example',
            },
        });
        await wrapper.setData({ selection: [{ id: 'selected-media' }] });
        expect(wrapper.findComponent('sw-media-sidebar-stub').props('items')).toHaveLength(1);

        await wrapper.findComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'extension-primitives-tab');
        await flushPromises();

        const iframe = wrapper.find('sw-iframe-renderer-stub');
        expect(iframe.attributes('src')).toBe('https://app.example');
        expect(iframe.attributes('location-id')).toBe('primitives-tab');
        expect(wrapper.findComponent('sw-media-sidebar-stub').props('items')).toEqual([]);
        expect(wrapper.find('.sw-media-modal-v2__library-content').attributes('style')).toBe('display: none;');
    });
});
