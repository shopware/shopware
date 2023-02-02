import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-media/component/sw-media-modal-v2';

describe('src/module/sw-media/component/sw-media-modal-v2', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-media-modal-v2'), {
            stubs: {
                'sw-modal': true,
                'sw-tabs': {
                    template: '<div><slot name="content" active="upload"></slot></div>'
                },
                'sw-media-sidebar': true,
                'sw-button': true,
                'sw-media-upload-v2': true,
                'sw-upload-listener': true,
                'sw-media-grid': true
            },
            provide: {
                repositoryFactory: {},
                mediaService: {}
            },
            propsData: {
                uploadTag: 'my-upload'
            }
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
            fileAccept: 'application/pdf'
        });
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('application/pdf');
    });
});
