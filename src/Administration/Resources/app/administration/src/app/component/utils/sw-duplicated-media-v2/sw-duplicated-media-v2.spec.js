/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-duplicated-media-v2';

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
    totalAmount: 1
};

describe('components/utils/sw-duplicated-media-v2', () => {
    let wrapper;
    let uploads = {};

    beforeEach(async () => {
        uploads = {};
        wrapper = shallowMount(await Shopware.Component.build('sw-duplicated-media-v2'), {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve([{ id: 'foo' }]),
                            get: () => Promise.resolve({ id: 'foo', hasFile: true }),
                            delete: () => Promise.resolve()
                        };
                    }
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
                    keepFile: jest.fn()
                }
            },
            stubs: {
                'sw-modal': true,
                'sw-container': true,
                'sw-media-preview-v2': true,
                'sw-icon': true,
                'sw-field': true,
                'sw-button': true,
                'sw-media-media-item': true
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should upload the renamed file', async () => {
        await wrapper.vm.renameFile(uploadTaskMock);

        const matchingUploadTask = uploads[uploadTaskMock.uploadTag].find(upload => {
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
});
