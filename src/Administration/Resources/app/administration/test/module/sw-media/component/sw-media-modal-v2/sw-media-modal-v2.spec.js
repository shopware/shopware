import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-media/component/sw-media-modal-v2';

describe('src/module/sw-media/component/sw-media-modal-v2', () => {
    let wrapper;

    beforeEach(() => {
        const localVue = createLocalVue();
        localVue.directive('droppable', {});

        wrapper = shallowMount(Shopware.Component.build('sw-media-modal-v2'), {
            localVue,
            stubs: {
                'sw-modal': true,
                'sw-tabs': '<div><slot name="content" active="upload"></slot></div>',
                'sw-media-sidebar': true,
                'sw-button': true,
                'sw-media-upload-v2': true,
                'sw-upload-listener': true,
                'sw-media-grid': true
            },
            mocks: {
                $t: v => v,
                $tc: v => v
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

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });


    it('should contain the default accept value', () => {
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes().fileaccept).toBe('image/*');
    });

    it('should contain "application/pdf" value', () => {
        wrapper.setProps({
            fileAccept: 'application/pdf'
        });
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes().fileaccept).toBe('application/pdf');
    });
});
