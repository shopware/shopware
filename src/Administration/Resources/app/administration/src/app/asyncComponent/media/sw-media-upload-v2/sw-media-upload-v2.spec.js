/**
 * @package content
 */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import SwMediaUploadV2 from 'src/app/asyncComponent/media/sw-media-upload-v2';
import 'src/app/component/base/sw-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/base/sw-button-group';

Shopware.Component.register('sw-media-upload-v2', SwMediaUploadV2);

async function createWrapper(customOptions = {}) {
    const localVue = createLocalVue();
    localVue.directive('droppable', {});

    return shallowMount(await Shopware.Component.build('sw-media-upload-v2'), {
        localVue,
        stubs: {
            'sw-icon': { template: '<div class="sw-icon" @click="$emit(\'click\')"></div>' },
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
            'sw-button-group': await Shopware.Component.build('sw-button-group'),
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
            'sw-media-url-form': true,
            'sw-popover': true,
            'sw-media-preview-v2': true
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => ({}),
                    saveAll: () => Promise.resolve({})
                })
            },
            mediaService: {
                addListener: () => {},
                addUploads: () => Promise.resolve(),
                removeByTag: () => {},
                removeListener: () => null,
            },
            configService: {
                getConfig: () => Promise.resolve({
                    settings: {
                        enableUrlFeature: true,
                    }
                })
            },
        },
        propsData: {
            uploadTag: 'my-upload',
            addFilesOnMultiselect: true,
        },
        ...customOptions
    });
}

let fileInput = null;
let fileInputValue = '';
let fileInputFilesGet;
let fileInputValueGet;
let fileInputValueSet;

describe('src/app/component/media/sw-media-upload-v2', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        fileInputFilesGet = jest.fn();
        fileInputValueGet = jest.fn().mockReturnValue(fileInputValue);
        fileInputValueSet = jest.fn().mockImplementation(v => {
            fileInputValue = v;
        });

        Object.defineProperty(fileInput.element, 'files', {
            get: fileInputFilesGet
        });

        Object.defineProperty(fileInput.element, 'value', {
            get: fileInputValueGet,
            set: fileInputValueSet,
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the default accept value', async () => {
        expect(fileInput.attributes().accept).toBe('image/*');
    });

    it('should contain "application/pdf" value', async () => {
        await wrapper.setProps({
            fileAccept: 'application/pdf'
        });

        expect(fileInput.attributes().accept).toBe('application/pdf');
    });

    it('should contain "image/jpeg","image/gif","image/png" values', async () => {
        await wrapper.setProps({
            fileAccept: 'image/jpeg,image/gif,image/png'
        });

        expect(fileInput.attributes().accept).toBe('image/jpeg,image/gif,image/png');
    });

    it('should contain mixed content-types value', async () => {
        await wrapper.setProps({
            fileAccept: 'image/jpeg,image/gif,image/png,application/pdf,image/x-eps'
        });

        expect(fileInput.attributes().accept).toBe('image/jpeg,image/gif,image/png,application/pdf,image/x-eps');
    });

    it('should contain all content-types value', async () => {
        await wrapper.setProps({
            fileAccept: '*/*'
        });

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
        expect(switchModeButton.attributes().class).toBe('sw-context-button sw-media-upload-v2__switch-mode is--disabled');
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
        wrapper = await createWrapper({
            listeners: {
                'media-upload-sidebar-open': jest.fn()
            }
        });

        const uploadButton = wrapper.find('.sw-media-upload-v2__button.open-media-sidebar');
        expect(uploadButton.attributes().disabled).not.toBeDefined();
    });

    it('open media sidebar button should be disabled', async () => {
        wrapper = await createWrapper({
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

    it('context button switch mode should change input type when clicking on menu item', async () => {
        await wrapper.setData({
            isUploadUrlFeatureEnabled: true,
            inputType: 'file-upload',
        });

        const switchModeButton = wrapper.find('.sw-media-upload-v2__switch-mode');
        expect(switchModeButton.exists()).toBeTruthy();

        // const fileInput = wrapper.find('.sw-media-upload-v2__file-input');
        expect(fileInput.exists()).toBeTruthy();

        let contextButton = wrapper.find('.sw-media-upload-v2__switch-mode button');
        await contextButton.trigger('click');

        let switchToUrlModeBtn = switchModeButton.find('.sw-media-upload-v2__button-url-upload');
        expect(switchToUrlModeBtn.exists()).toBeTruthy();

        let switchToFileModeBtn = switchModeButton.find('.sw-media-upload-v2__button-file-upload');
        expect(switchToFileModeBtn.exists()).toBeFalsy();

        await switchToUrlModeBtn.trigger('click');

        contextButton = wrapper.find('.sw-media-upload-v2__switch-mode button');
        await contextButton.trigger('click');

        switchToFileModeBtn = switchModeButton.find('.sw-media-upload-v2__button-file-upload');
        switchToUrlModeBtn = switchModeButton.find('.sw-media-upload-v2__button-url-upload');

        expect(switchToFileModeBtn.exists()).toBeTruthy();
        expect(switchToUrlModeBtn.exists()).toBeFalsy();

        const urlForm = wrapper.find('.sw-media-upload-v2__url-form');

        expect(urlForm.exists()).toBeTruthy();
    });

    it('should show media form when select upload by url option', async () => {
        await wrapper.setData({
            isUploadUrlFeatureEnabled: true,
            inputType: 'file-upload',
        });
        await flushPromises();

        expect(wrapper.vm.inputType).toBe('file-upload');

        const contextButton = wrapper.find('.sw-media-upload-v2__switch-mode button');
        await contextButton.trigger('click');

        const uploadOption = wrapper.find('.sw-context-menu-item');
        expect(uploadOption.text()).toEqual('global.sw-media-upload-v2.buttonUrlUpload');
    });

    it('open media button should have normal style shade when variant is regular', async () => {
        wrapper = await createWrapper({
            listeners: {
                'media-upload-sidebar-open': jest.fn()
            }
        });

        const openMediaButton = wrapper.find('.open-media-sidebar');

        expect(openMediaButton.find('.sw-icon').exists()).toBeFalsy();
        expect(openMediaButton.text()).toEqual('global.sw-media-upload-v2.buttonOpenMedia');
    });

    it('open media button should have square shade when variant is compact', async () => {
        wrapper = await createWrapper({
            listeners: {
                'media-upload-sidebar-open': jest.fn()
            }
        });

        await wrapper.setProps({
            variant: 'small'
        });

        const openMediaButton = wrapper.find('.open-media-sidebar');

        expect(openMediaButton.classes()).toContain('sw-button--square');
        expect(openMediaButton.find('.sw-icon').exists()).toBeTruthy();
    });

    it('should show error notification able file type is not suitable', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setProps({
            fileAccept: 'image/jpg',
        });

        fileInputValue = 'dummy.pdf';
        fileInputFilesGet.mockReturnValue([{
            size: 12345,
            name: 'dummy.pdf',
            type: 'application/pdf'
        }]);

        await fileInput.trigger('change');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-upload-v2.notification.invalidFileType.message',
            title: 'global.default.error',
        });
    });

    it('should show error notification able file size is not suitable', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setProps({
            fileAccept: 'image/jpg',
            maxFileSize: 2000,
        });

        fileInputValue = 'dummy.jpg';
        fileInputFilesGet.mockReturnValue([{
            size: 12345,
            name: 'dummy.jpg',
            type: 'image/jpg'
        }]);

        await fileInput.trigger('change');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-upload-v2.notification.invalidFileSize.message',
            title: 'global.default.error',
        });
    });

    it('should able emit "media-upload-add-file" event when file type and file size are matched', async () => {
        await wrapper.setProps({
            fileAccept: 'application/pdf',
            maxFileSize: 2000,
            useFileData: true,
        });

        fileInputValue = 'dummy.pdf';
        fileInputFilesGet.mockReturnValue([{
            size: 1234,
            name: 'dummy.pdf',
            type: 'application/pdf'
        }]);

        await fileInput.trigger('change');
        expect(wrapper.emitted('media-upload-add-file')[0][0]).toEqual([{
            size: 1234,
            name: 'dummy.pdf',
            type: 'application/pdf'
        }]);
    });

    it('should emit media-upload-remove-image event when removing file', async () => {
        await wrapper.setProps({
            source: {
                fileName: 'test',
                fileExtension: 'jpg'
            },
        });

        expect(wrapper.find('.sw-media-upload-v2__file-headline').text()).toEqual('test.jpg');

        const removeFileButton = wrapper.find('.sw-media-upload-v2__remove-icon');
        await removeFileButton.trigger('click');

        expect(wrapper.emitted('media-upload-remove-image')).toBeTruthy();
    });

    it('should show an indicator when the component requires files', async () => {
        await wrapper.setProps({
            label: 'some label',
            required: true
        });

        expect(wrapper.find('.sw-media-upload-v2__label').classes()).toContain('is--required');
    });

    it('should handle file upload in single file mode', async () => {
        wrapper = await createWrapper({
            propsData: {
                allowMultiSelect: false,
                addFilesOnMultiselect: false,
                uploadTag: 'my-upload'
            }
        });
        wrapper.vm.mediaRepository.saveAll = jest.fn();

        await wrapper.vm.handleUpload([new File([''], 'foo.jpg'), new File([''], 'bar.gif')]);

        expect(wrapper.vm.mediaRepository.saveAll).toHaveBeenCalled();
    });

    it('should show a single preview in single mode', async () => {
        wrapper = await createWrapper({
            propsData: {
                uploadTag: 'my-upload',
                allowMultiSelect: false,
                addFilesOnMultiselect: false,
            }
        });

        await wrapper.vm.handleUpload([new File([''], 'foo.jpg')]);

        expect(Array.isArray(wrapper.vm.preview)).toBe(false);
    });

    it('should show multiple preview in multi mode', async () => {
        wrapper = await createWrapper();

        await wrapper.vm.handleUpload([new File([''], 'foo.jpg'), new File([''], 'bar.gif')]);

        expect(Array.isArray(wrapper.vm.preview)).toBe(true);
    });

    it('should check file type correct no matter in which sequence the accept types were set', async () => {
        const file = {
            name: 'dummy.pdf',
            type: 'application/pdf'
        };

        await wrapper.setProps({
            fileAccept: 'application/pdf, image/*'
        });

        let isTypeAccepted = wrapper.vm.checkFileType(file);
        expect(isTypeAccepted).toBeTruthy();

        await wrapper.setProps({
            fileAccept: 'image/*, application/pdf'
        });

        isTypeAccepted = wrapper.vm.checkFileType(file);
        expect(isTypeAccepted).toBeTruthy();
    });

    it('should allow wildcard in the chunck of the file type', async () => {
        await wrapper.setProps({
            fileAccept: '*/svg'
        });

        let isTypeAccepted = wrapper.vm.checkFileType({
            name: 'dummy.png',
            type: 'image/png'
        });
        expect(isTypeAccepted).toBeFalsy();

        isTypeAccepted = wrapper.vm.checkFileType({
            name: 'dummy.svg',
            type: 'image/svg'
        });
        expect(isTypeAccepted).toBeTruthy();
    });
});

