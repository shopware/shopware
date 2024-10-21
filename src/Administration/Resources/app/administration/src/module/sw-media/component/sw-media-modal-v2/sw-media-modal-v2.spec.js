/**
 * @package content
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-media/component/sw-media-modal-v2', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-media-modal-v2', { sync: true }), {
            props: {
                uploadTag: 'my-upload',
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-modal': true,
                    'sw-tabs': {
                        template: '<div><slot name="content" active="upload"></slot></div>',
                    },
                    'sw-media-sidebar': true,
                    'sw-button': true,
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
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
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
});
