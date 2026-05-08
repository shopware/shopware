/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-media/component/sw-media-modal-v2', () => {
    let wrapper;

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
                        name: 'sw-tabs',
                        template: '<div><slot name="content" active="upload"></slot></div>',
                    },
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: ['items', 'defaultItem', 'positionIdentifier'],
                        template: '<div />',
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

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        global.activeFeatureFlags = [];
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

    it('should render legacy sw-tabs when the major migration is inactive', async () => {
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render mt-tabs with modal items when the major migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        wrapper = await createWrapper({
            defaultTab: 'upload',
        });

        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(mtTabs.props('positionIdentifier')).toBe('sw-media-modal');
        expect(mtTabs.props('defaultItem')).toBe('upload');
        expect(mtTabs.props('items')).toEqual([
            {
                label: 'sw-media.sw-media-modal-v2.labelTabItemLibrary',
                name: 'library',
                disabled: false,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-media.sw-media-modal-v2.labelTabItemUpload',
                name: 'upload',
                onClick: expect.any(Function),
            },
        ]);
    });
});
