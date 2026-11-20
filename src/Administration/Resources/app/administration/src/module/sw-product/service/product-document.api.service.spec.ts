/**
 * @sw-package inventory
 */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';
import ProductDocumentApiService from './product-document.api.service';

function createService(get: jest.Mock) {
    return new ProductDocumentApiService(
        { get } as unknown as HttpClient,
        { getToken: () => 'token' } as unknown as LoginService,
    );
}

describe('module/sw-product/service/product-document.api.service', () => {
    it('requests the document upload settings route and returns its settings', async () => {
        const settings = {
            allowedExtensions: ['pdf'],
            allowedMimeTypesByExtension: { pdf: ['application/pdf'] },
            maxFileSize: 1024,
        };
        const get = jest.fn().mockResolvedValue({ data: settings });

        await expect(createService(get).getUploadSettings()).resolves.toStrictEqual(settings);
        expect(get).toHaveBeenCalledWith('/_action/product/document-upload-settings', expect.any(Object));
    });

    it('passes on the error when the request fails', async () => {
        const error = new Error('Request failed');
        const get = jest.fn().mockRejectedValue(error);

        await expect(createService(get).getUploadSettings()).rejects.toBe(error);
    });
});
