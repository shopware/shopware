/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

const uploadTaskMock = {
    running: false,
    src: File,
    uploadTag: 'upload-tag-sw-media-index',
    targetId: 'aaaef50651e04f59bbc9c309b5110e23',
    fileName: 'my-demo-image',
    extension: 'jpg',
    error: null,
    successAmount: 0,
    failureAmount: 1,
    totalAmount: 1,
};

describe('components/utils/sw-duplicated-media-v2', () => {
    let wrapper;
    let uploads = {};

    beforeEach(async () => {
        uploads = {};
        wrapper = mount(await wrapTestComponent('sw-duplicated-media-v2', { sync: true }), {
            global: {
                provide: {
                    shortcutService: {
                        startEventListener() {},
                        stopEventListener() {},
                    },
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve([{ id: 'foo' }]),
                                get: () =>
                                    Promise.resolve({
                                        id: 'foo',
                                        hasFile: true,
                                    }),
                                delete: () => Promise.resolve(),
                            };
                        },
                    },
                    mediaService: {
                        addDefaultListener: jest.fn(),
                        removeDefaultListener: jest.fn(),
                        runUploads: jest.fn(),
                        addUpload: (tag, uploadTask) => {
                            if (!uploads[tag]) uploads[tag] = [];

                            uploads[tag].push(uploadTask);
                        },
                        provideName: async (fileName) => {
                            return { fileName: `${fileName}_(2)` };
                        },
                        keepFile: jest.fn(),
                    },
                },
                stubs: {
                    'sw-modal': {
                        template: `
                            <div class="sw-modal">
                                <slot name="modal-header">
                                    <slot name="modal-title"></slot>
                                </slot>
                                <slot name="modal-body">
                                     <slot></slot>
                                </slot>
                                <slot name="modal-footer">
                                </slot>
                            </div>
                        `,
                    },
                    'sw-container': true,
                    'sw-media-preview-v2': true,
                    'sw-icon': true,
                    'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-media-media-item': true,
                    'sw-checkbox-field': true,
                    'mt-button': true,
                    'router-link': true,
                    'sw-loader': true,
                    'sw-help-text': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should upload the renamed file', async () => {
        await wrapper.vm.renameFile(uploadTaskMock);

        const matchingUploadTask = uploads[uploadTaskMock.uploadTag].find((upload) => {
            return upload.targetId === uploadTaskMock.targetId;
        });

        expect(matchingUploadTask.fileName).toBe(`${uploadTaskMock.fileName}_(2)`);
        expect(wrapper.vm.mediaService.runUploads).toHaveBeenCalledWith('upload-tag-sw-media-index');
    });

    it('should keep the existing file', async () => {
        wrapper.vm.defaultOption = 'Keep';
        await wrapper.setData({ failedUploadTasks: [uploadTaskMock] });

        await wrapper.vm.solveDuplicate();
        await wrapper.vm.$nextTick();

        const expectedTask = { ...uploadTaskMock, ...{ targetId: 'foo' } };

        expect(wrapper.vm.mediaService.keepFile).toHaveBeenCalledWith(expectedTask.uploadTag, expectedTask);
    });

    it('should replace the file on the server with the local file', async () => {
        wrapper.vm.defaultOption = 'Replace';
        await wrapper.setData({ failedUploadTasks: [uploadTaskMock] });
        await flushPromises();

        const radio = wrapper.find('input[type="radio"]');
        await radio.setValue('checked');

        const replaceButton = wrapper.find('.sw-duplicated-media-v2__upload');
        await replaceButton.trigger('click');

        expect(wrapper.vm.mediaService.runUploads).toHaveBeenCalledWith('upload-tag-sw-media-index');
    });
});
