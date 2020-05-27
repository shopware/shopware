import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/media/sw-media-upload-v2';
import 'src/app/component/media/sw-media-compact-upload-v2';

describe('src/app/component/media/sw-media-compact-upload-v2', () => {
    let wrapper;

    beforeEach(() => {
        const localVue = createLocalVue();
        localVue.directive('droppable', {});

        wrapper = shallowMount(Shopware.Component.build('sw-media-compact-upload-v2'), {
            localVue,
            stubs: {
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-button': true
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
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('image/*');
    });

    it('should contain "application/pdf" value', () => {
        wrapper.setProps({
            fileAccept: 'application/pdf'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('application/pdf');
    });
});
