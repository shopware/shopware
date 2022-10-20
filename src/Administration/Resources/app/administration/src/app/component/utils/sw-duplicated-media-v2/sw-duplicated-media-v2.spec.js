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

    beforeEach(() => {
        uploads = {};
        wrapper = shallowMount(Shopware.Component.build('sw-duplicated-media-v2'), {
            provide: {
                repositoryFactory: {},
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
                    }
                }
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
});
