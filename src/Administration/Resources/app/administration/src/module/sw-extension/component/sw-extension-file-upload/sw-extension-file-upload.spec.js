import { mount } from '@vue/test-utils';

const uploadSpy = jest.fn(() => Promise.resolve({}));
const updateExtensionDataSpy = jest.fn(() => Promise.resolve({}));
const userConfigSaveSpy = jest.fn(() => Promise.resolve({}));

async function createWrapper(userConfig = {}) {
    const wrapper = mount(await wrapTestComponent('sw-extension-file-upload', { sync: true }), {
        global: {
            stubs: {
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-icon': true,
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-field-error': true,
                'sw-modal': {
                    props: ['title'],
                    // eslint-disable-next-line max-len
                    template: '<div><div class="sw-modal__title">{{ title }}</div><div class="sw-modal__body"><slot/></div><slot name="modal-footer"></slot></div>',
                },
                'sw-loader': true,
            },
            provide: {
                extensionStoreActionService: {
                    upload: uploadSpy,
                },
                repositoryFactory: {
                    create: () => {
                        return {};
                    },
                },
            },
        },
        computed: {
            userConfigRepository: () => ({
                search() {
                    return Promise.resolve(userConfig);
                },
                create() {
                    return Promise.resolve({});
                },
                save: userConfigSaveSpy,
            }),
        },
        attachTo: document.body,
    });

    await flushPromises();

    return wrapper;
}

function createFile(size = 44320, name = 'test-plugin.zip', type = 'application/zip') {
    return new File([new ArrayBuffer(size)], name, {
        type: type,
    });
}

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-extension-file-upload', () => {
    beforeAll(() => {
        Shopware.Service().register('shopwareExtensionService', () => {
            return {
                updateExtensionData: updateExtensionDataSpy,
            };
        });
    });

    beforeEach(async () => {
        Shopware.State.get('notification').notifications = {};
        Shopware.State.get('notification').growlNotifications = {};
    });

    it('should show warning modal and then call the file input form', async () => {
        const wrapper = await createWrapper();

        // spy for file input click
        const fileInput = wrapper.get('.sw-extension-file-upload__file-input');
        jest.spyOn(fileInput.element, 'click');

        expect(wrapper.find('.sw-extension-file-upload-confirm-modal').exists()).toBe(false);

        // click on upload
        const uploadButton = wrapper.get('.sw-extension-file-upload__button');
        await uploadButton.trigger('click');

        await wrapper.vm.$nextTick();
        await flushPromises();

        const warningModal = wrapper.get('.sw-extension-file-upload-confirm-modal');

        // fileInput has not been clicked before
        expect(fileInput.element.click).not.toHaveBeenCalled();

        const continueButton = warningModal.get('.sw-button--primary');
        await continueButton.trigger('click');

        // expect that the input gets clicked
        expect(fileInput.element.click).toHaveBeenCalled();
    });

    it('should not show warning modal if its hidden by user config', async () => {
        const wrapper = await createWrapper();

        // spy for file input click
        const fileInput = wrapper.find('.sw-extension-file-upload__file-input');
        jest.spyOn(fileInput.element, 'click');

        wrapper.vm.pluginUploadUserConfig = {
            key: 'extension.plugin_upload',
            userId: 'abc',
            value: {
                hide_upload_warning: true,
            },
        };

        // fileInput has not been clicked before
        expect(fileInput.element.click).not.toHaveBeenCalled();

        // click on upload
        const uploadButton = wrapper.find('.sw-extension-file-upload__button');
        await uploadButton.trigger('click');

        const warningModal = wrapper.find('.sw-extension-file-upload-confirm-modal');
        expect(warningModal.exists()).toBe(false);

        // expect that the input gets clicked
        expect(fileInput.element.click).toHaveBeenCalled();
    });

    it('should update user config on file upload', async () => {
        const wrapper = await createWrapper();

        // spy for file input click
        const fileInput = wrapper.get('.sw-extension-file-upload__file-input');
        jest.spyOn(fileInput.element, 'click');

        wrapper.vm.pluginUploadUserConfig = {
            key: 'extension.plugin_upload',
            userId: 'abc',
            value: {
                hide_upload_warning: false,
            },
        };

        // click on upload
        const uploadButton = wrapper.get('.sw-extension-file-upload__button');
        await uploadButton.trigger('click');

        const warningModal = wrapper.get('.sw-extension-file-upload-confirm-modal');

        const hideCheckbox = warningModal.get('input[type=\'checkbox\']');
        await hideCheckbox.setChecked();

        await wrapper.vm.handleUpload([createFile()]);

        expect(userConfigSaveSpy).toHaveBeenCalled();
        expect(userConfigSaveSpy.mock.calls[0][0]).toEqual({
            key: 'extension.plugin_upload',
            userId: 'abc',
            value: {
                hide_upload_warning: true,
            },
        });
    });

    it('should upload the correct file when user selects a file', async () => {
        const wrapper = await createWrapper();

        // upload a file
        const fileInput = wrapper.find('.sw-extension-file-upload__file-input');
        const mockFile = createFile();

        Object.defineProperty(fileInput.element, 'files', {
            value: [mockFile],
        });

        // trigger file change
        await fileInput.trigger('change');

        // check if upload gets called with correct file
        const formDataMock = new FormData();
        formDataMock.append('file', mockFile);

        expect(uploadSpy).toHaveBeenCalledWith(formDataMock);

        // check if installed extensions get updated
        expect(updateExtensionDataSpy).toHaveBeenCalled();
    });

    it('should throw an error if the upload goes wrong', async () => {
        const wrapper = await createWrapper();

        // no growl message was thrown
        expect(Object.keys(Shopware.State.get('notification').growlNotifications)).toHaveLength(0);

        // return an error from the upload
        // eslint-disable-next-line prefer-promise-reject-errors
        uploadSpy.mockImplementationOnce(() => Promise.reject({
            response: {
                data: {
                    errors: [
                        'Wrong file format',
                    ],
                },
            },
        }));

        // upload a wrong file
        const fileInput = wrapper.find('.sw-extension-file-upload__file-input');
        Object.defineProperty(fileInput.element, 'files', {
            value: ['wrongFile'],
        });

        // trigger file change
        await fileInput.trigger('change');

        // check if error notification gets thrown
        await wrapper.vm.$nextTick();
        const growlNotifications = Shopware.State.get('notification').growlNotifications;

        expect(Object.keys(growlNotifications)).toHaveLength(1);
        Object.keys(growlNotifications).forEach(key => {
            expect(growlNotifications[key]).toHaveProperty('message');
            expect(growlNotifications[key].message).toBe('sw-extension.errors.messageGenericFailure');
            expect(growlNotifications[key]).toHaveProperty('title');
            expect(growlNotifications[key].title).toBe('global.default.error');
            expect(growlNotifications[key]).toHaveProperty('variant');
            expect(growlNotifications[key].variant).toBe('error');
        });
    });
});
