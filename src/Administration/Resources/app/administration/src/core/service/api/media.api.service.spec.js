import MediaApiService from 'src/core/service/api/media.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

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

function getMediaApiService(client = null, loginService = null) {
    if (client === null) {
        client = createHTTPClient();
    }

    if (loginService === null) {
        loginService = createLoginService(client, Shopware.Context.api);
    }

    return new MediaApiService(client, loginService);
}

describe('storeService', () => {
    it('is registered correctly', async () => {
        expect(getMediaApiService()).toBeInstanceOf(MediaApiService);
    });

    it('handles keeping files', async () => {
        const mediaApiService = getMediaApiService();
        const callback = jest.fn();
        const event = mediaApiService._createUploadEvent(
            'media-upload-finish',
            uploadTaskMock.uploadTag,
            {
                targetId: uploadTaskMock.targetId,
                successAmount: 0,
                failureAmount: 0,
                totalAmount: 0,
                customMessage: 'global.sw-media-upload.notification.assigned.message',
            },
        );
        mediaApiService.addListener(uploadTaskMock.uploadTag, callback);

        mediaApiService.keepFile(uploadTaskMock.uploadTag, uploadTaskMock);

        expect(callback).toHaveBeenCalledWith(event);
    });

    it('uploadMediaById with glb-file extension and detected Content-Type is empty will set Content-Type to `model/gltf-binary`', () => {
        const mediaApiService = getMediaApiService();
        const httpClientPostSpy = jest.spyOn(mediaApiService.httpClient, 'post');

        mediaApiService.uploadMediaById('test', '', {}, 'glb', 'test');

        expect(httpClientPostSpy.mock.calls[0][2].headers['Content-Type']).toBe('model/gltf-binary');
    });
});
