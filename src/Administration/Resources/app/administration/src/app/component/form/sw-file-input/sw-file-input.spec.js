/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-file-input';
import 'src/app/component/base/sw-button';
import 'src/app/component/context-menu/sw-context-menu-item';

async function createWrapper(customOptions = {}) {
    return mount(await wrapTestComponent('sw-file-input', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': { template: '<div class="sw-icon" @click="$emit(\'click\')"></div>' },
                'sw-button': await Shopware.Component.build('sw-button'),
            },
        },
        ...customOptions,
    });
}

let fileInput = null;
let fileInputValue = '';
let fileInputFilesGet;
let fileInputValueGet;
let fileInputValueSet;

describe('src/app/component/form/sw-file-input', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        fileInput = wrapper.find('.sw-file-input__file-input');

        fileInputFilesGet = jest.fn();
        fileInputValueGet = jest.fn().mockReturnValue(fileInputValue);
        fileInputValueSet = jest.fn().mockImplementation(v => {
            fileInputValue = v;
        });

        Object.defineProperty(fileInput.element, 'files', {
            get: fileInputFilesGet,
        });

        Object.defineProperty(fileInput.element, 'value', {
            get: fileInputValueGet,
            set: fileInputValueSet,
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('upload button should be enabled', async () => {
        const uploadButton = wrapper.find('.sw-file-input__button');
        expect(uploadButton.attributes().disabled).toBeUndefined();
    });

    it('upload button should be disabled', async () => {
        await wrapper.setProps({
            disabled: true,
        });

        const uploadButton = wrapper.find('.sw-file-input__button');
        expect(uploadButton.attributes().disabled).toBeDefined();
    });

    it('should show error notification able file type is not suitable', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setProps({
            allowedMimeTypes: ['image/jpg'],
        });

        fileInputValue = 'dummy.pdf';
        fileInputFilesGet.mockReturnValue([{
            size: 12345,
            name: 'dummy.pdf',
            type: 'application/pdf',
        }]);

        await fileInput.trigger('change');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-file-input.notification.invalidFileType.message',
            title: 'global.default.error',
        });
    });

    it('should show error notification able file size is not suitable', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setProps({
            maxFileSize: 2000,
        });

        fileInputValue = 'dummy.jpg';
        fileInputFilesGet.mockReturnValue([{
            size: 12345,
            name: 'dummy.jpg',
            type: 'image/jpg',
        }]);

        await fileInput.trigger('change');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-file-input.notification.invalidFileSize.message',
            title: 'global.default.error',
        });
    });

    it('should able to show file name when file type and file size are matched', async () => {
        await wrapper.setProps({
            allowMimeTypes: ['application/pdf'],
            maxFileSize: 2000,
        });

        fileInputValue = 'dummy.pdf';
        fileInputFilesGet.mockReturnValue([{
            size: 1234,
            name: 'dummy.pdf',
            type: 'application/pdf',
        }]);

        await fileInput.trigger('change');

        expect(wrapper.vm.selectedFile).toEqual({
            size: 1234,
            name: 'dummy.pdf',
            type: 'application/pdf',
        });
    });

    it('should not show header and remove icon', async () => {
        const fileName = wrapper.find('.sw-file-input__file-headline');
        const removeIcon = wrapper.find('.sw-file-input__remove-icon');

        expect(removeIcon.exists()).toBeFalsy();
        expect(fileName.exists()).toBeFalsy();
    });

    it('should show header and remove icon', async () => {
        await wrapper.setData({
            selectedFile: {
                size: 1234,
                name: 'dummy.pdf',
                type: 'application/pdf',
            },
        });

        const fileName = wrapper.find('.sw-file-input__file-headline');
        const removeIcon = wrapper.find('.sw-file-input__remove-icon');

        expect(removeIcon.exists()).toBeTruthy();
        expect(fileName.exists()).toBeTruthy();

        expect(fileName.text()).toBe('dummy.pdf');
    });

    it('should able to remove file when click on remove icon', async () => {
        await wrapper.setData({
            selectedFile: {
                size: 1234,
                name: 'dummy.pdf',
                type: 'application/pdf',
            },
        });

        let removeIcon = wrapper.find('.sw-file-input__remove-icon');
        await removeIcon.trigger('click');

        expect(wrapper.vm.selectedFile).toBeNull();

        removeIcon = wrapper.find('.sw-file-input__remove-icon');
        expect(removeIcon.exists()).toBeFalsy();
    });
});
