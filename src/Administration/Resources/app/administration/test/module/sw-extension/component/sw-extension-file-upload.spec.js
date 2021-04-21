import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extension-file-upload';
import 'src/app/component/base/sw-button';

const uploadSpy = jest.fn(() => Promise.resolve({}));
const updateExtensionDataSpy = jest.fn();

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-extension-file-upload'), {
        stubs: {
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-icon': true
        },
        provide: {
            extensionStoreActionService: {
                upload: uploadSpy
            },
            repositoryFactory: {
                create: () => {
                    return {};
                }
            }
        }
    });
}

describe('src/module/sw-extension/page/sw-extension-my-extensions-account', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        Shopware.Service().register('shopwareExtensionService', () => {
            return {
                updateExtensionData: updateExtensionDataSpy
            };
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
        uploadSpy.mockClear();
        updateExtensionDataSpy.mockClear();
        Shopware.State.get('notification').notifications = {};
        Shopware.State.get('notification').growlNotifications = {};
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should call the file input form', async () => {
        // spy for file input click
        const fileInput = wrapper.find('.sw-extension-file-upload__file-input');
        jest.spyOn(fileInput.element, 'click');

        // fileInput has not been clicked before
        expect(fileInput.element.click).not.toHaveBeenCalled();

        // click on upload
        const uploadButton = wrapper.find('.sw-extension-file-upload__button');
        await uploadButton.trigger('click');

        // expect that the input gets clicked
        expect(fileInput.element.click).toHaveBeenCalled();
    });

    it('should upload the correct file when user selects a file', async () => {
        // upload a file
        const fileInput = wrapper.find('.sw-extension-file-upload__file-input');
        const mockFile = createFile();

        Object.defineProperty(fileInput.element, 'files', {
            value: [mockFile]
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
        // no growl message was thrown
        expect(Object.keys(Shopware.State.get('notification').growlNotifications).length).toBe(0);

        // return an error from the upload
        // eslint-disable-next-line prefer-promise-reject-errors
        uploadSpy.mockImplementationOnce(() => Promise.reject({
            response: {
                data: {
                    errors: [
                        'Wrong file format'
                    ]
                }
            }
        }));

        // upload a wrong file
        const fileInput = wrapper.find('.sw-extension-file-upload__file-input');
        Object.defineProperty(fileInput.element, 'files', {
            value: ['wrongFile']
        });

        // trigger file change
        await fileInput.trigger('change');

        // check if error notification gets thrown
        await wrapper.vm.$nextTick();
        const growlNotifications = Shopware.State.get('notification').growlNotifications;

        expect(Object.keys(growlNotifications).length).toBe(1);
        Object.keys(growlNotifications).forEach(key => {
            expect(growlNotifications[key]).toHaveProperty('message');
            expect(growlNotifications[key].message).toEqual('sw-extension.errors.messageGenericFailure');
            expect(growlNotifications[key]).toHaveProperty('title');
            expect(growlNotifications[key].title).toEqual('global.default.error');
            expect(growlNotifications[key]).toHaveProperty('variant');
            expect(growlNotifications[key].variant).toEqual('error');
        });
    });
});

function createFile(size = 44320, name = 'test-plugin.zip', type = 'application/zip') {
    return new File([new ArrayBuffer(size)], name, {
        type: type
    });
}
