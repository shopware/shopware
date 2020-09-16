import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/media/sw-media-upload-v2';

function createWrapper(customOptions = {}, privileges = []) {
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
            configService: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
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
        wrapper = createWrapper({}, [
            'media.editor'
        ]);
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the default accept value', async () => {
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('image/*');
    });

    it('should contain "application/pdf" value', async () => {
        await wrapper.setProps({
            fileAccept: 'application/pdf'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('application/pdf');
    });

    it('should contain "image/jpeg","image/gif","image/png" values', async () => {
        await wrapper.setProps({
            fileAccept: 'image/jpeg,image/gif,image/png'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('image/jpeg,image/gif,image/png');
    });

    it('should contain mixed content-types value', async () => {
        await wrapper.setProps({
            fileAccept: 'image/jpeg,image/gif,image/png,application/pdf,image/x-eps'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('image/jpeg,image/gif,image/png,application/pdf,image/x-eps');
    });

    it('should contain all content-types value', async () => {
        await wrapper.setProps({
            fileAccept: '*/*'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('*/*');
    });

    it('context button should be enabled', async () => {
        await wrapper.setProps({
            variant: 'compact'
        });
        await wrapper.setData({
            isUploadUrlFeatureEnabled: true
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button-context-menu');
        expect(uploadButton.exists()).toBeTruthy();
    });

    it('context button should be disabled', async () => {
        await wrapper.setProps({
            variant: 'compact',
            disabled: true
        });
        await wrapper.setData({
            isUploadUrlFeatureEnabled: true
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button-context-menu');
        expect(uploadButton.attributes().disabled).toBeTruthy();
    });

    it('context button switch mode should be enabled', async () => {
        await wrapper.setData({
            isUploadUrlFeatureEnabled: true
        });

        const switchModeButton = wrapper.find('.sw-media-upload-v2__switch-mode');
        expect(switchModeButton.exists()).toBeTruthy();
    });

    it('context button switch mode should be disabled', async () => {
        await wrapper.setProps({
            disabled: true
        });
        await wrapper.setData({
            isUploadUrlFeatureEnabled: true
        });

        const switchModeButton = wrapper.find('.sw-media-upload-v2__switch-mode');
        expect(switchModeButton.attributes().disabled).toBeTruthy();
    });

    it('remove icon should be enabled', async () => {
        await wrapper.setProps({
            source: '1a2b3c'
        });

        const removeIcon = wrapper.find('.sw-media-upload-v2__remove-icon');
        expect(removeIcon.exists()).toBeTruthy();
    });

    it('remove icon should be disabled', async () => {
        await wrapper.setProps({
            source: '1a2b3c',
            disabled: true
        });

        const removeIcon = wrapper.find('.sw-media-upload-v2__remove-icon');
        expect(removeIcon.exists()).toBeFalsy();
    });

    it('upload button should be enabled', async () => {
        const uploadButton = wrapper.find('.sw-media-upload-v2__button.upload');
        expect(uploadButton.attributes().disabled).not.toBeDefined();
    });

    it('upload button should be disabled', async () => {
        await wrapper.setProps({
            disabled: true
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button.upload');
        expect(uploadButton.attributes().disabled).toBeDefined();
    });

    it('open media sidebar button should be enabled', async () => {
        wrapper = createWrapper({
            listeners: {
                'media-upload-sidebar-open': jest.fn()
            }
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button.open-media-sidebar');
        expect(uploadButton.attributes().disabled).not.toBeDefined();
    });

    it('open media sidebar button should be disabled', async () => {
        wrapper = createWrapper({
            listeners: {
                'media-upload-sidebar-open': jest.fn()
            }
        });

        await wrapper.setProps({
            disabled: true
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button.open-media-sidebar');
        expect(uploadButton.attributes().disabled).toBeDefined();
    });
});

