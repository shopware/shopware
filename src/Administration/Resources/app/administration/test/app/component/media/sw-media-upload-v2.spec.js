import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/media/sw-media-upload-v2';

function createWrapper(customOptions = {}) {
    const localVue = createLocalVue();
    localVue.directive('droppable', {});

    return shallowMount(Shopware.Component.build('sw-media-upload-v2'), {
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
            mediaService: {},
            configService: {}
        },
        propsData: {
            uploadTag: 'my-upload'
        },
        ...customOptions
    });
}

describe('src/app/component/media/sw-media-upload-v2', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
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

    it('should contain "image/jpeg","image/gif","image/png" values', () => {
        wrapper.setProps({
            fileAccept: 'image/jpeg,image/gif,image/png'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('image/jpeg,image/gif,image/png');
    });

    it('should contain mixed content-types value', () => {
        wrapper.setProps({
            fileAccept: 'image/jpeg,image/gif,image/png,application/pdf,image/x-eps'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('image/jpeg,image/gif,image/png,application/pdf,image/x-eps');
    });

    it('should contain all content-types value', () => {
        wrapper.setProps({
            fileAccept: '*/*'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('*/*');
    });

    it('context button should be enabled', () => {
        wrapper.setProps({
            variant: 'compact'
        });
        wrapper.vm.isUploadUrlFeatureEnabled = true;

        const uploadButton = wrapper.find('.sw-media-upload-v2__button-context-menu');
        expect(uploadButton.exists()).toBeTruthy();
    });

    it('context button should be disabled', () => {
        wrapper.setProps({
            variant: 'compact',
            disabled: true
        });
        wrapper.vm.isUploadUrlFeatureEnabled = true;

        const uploadButton = wrapper.find('.sw-media-upload-v2__button-context-menu');
        expect(uploadButton.exists()).toBeFalsy();
    });

    it('context button switch mode should be enabled', () => {
        wrapper.setData({
            isUploadUrlFeatureEnabled: true
        });

        const switchModeButton = wrapper.find('.sw-media-upload-v2__switch-mode');
        expect(switchModeButton.exists()).toBeTruthy();
    });

    it('context button switch mode should be disabled', () => {
        wrapper.setProps({
            disabled: true
        });
        wrapper.setData({
            isUploadUrlFeatureEnabled: true
        });

        const switchModeButton = wrapper.find('.sw-media-upload-v2__switch-mode');
        expect(switchModeButton.exists()).toBeFalsy();
    });

    it('remove icon should be enabled', () => {
        wrapper.setProps({
            source: '1a2b3c'
        });

        const removeIcon = wrapper.find('.sw-media-upload-v2__remove-icon');
        expect(removeIcon.exists()).toBeTruthy();
    });

    it('remove icon should be disabled', () => {
        wrapper.setProps({
            source: '1a2b3c',
            disabled: true
        });

        const removeIcon = wrapper.find('.sw-media-upload-v2__remove-icon');
        expect(removeIcon.exists()).toBeFalsy();
    });

    it('upload button should be enabled', () => {
        const uploadButton = wrapper.find('.sw-media-upload-v2__button.upload');
        expect(uploadButton.attributes().disabled).not.toBeDefined();
    });

    it('upload button should be disabled', () => {
        wrapper.setProps({
            disabled: true
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button.upload');
        expect(uploadButton.attributes().disabled).toBeDefined();
    });

    it('open media sidebar button should be enabled', () => {
        wrapper = createWrapper({
            listeners: {
                'media-upload-sidebar-open': jest.fn()
            }
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button.open-media-sidebar');
        expect(uploadButton.attributes().disabled).not.toBeDefined();
    });

    it('open media sidebar button should be disabled', () => {
        wrapper = createWrapper({
            listeners: {
                'media-upload-sidebar-open': jest.fn()
            }
        });

        wrapper.setProps({
            disabled: true
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button.open-media-sidebar');
        expect(uploadButton.attributes().disabled).toBeDefined();
    });
});
