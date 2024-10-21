/**
 * @package admin
 */

import ExtensionSdkService from 'src/core/service/api/extension-sdk.service';

describe('src/core/service/api/extension-sdk.service', () => {
    it('should call the sign-uri route', async () => {
        const httpClientMock = {
            post: jest.fn(() => Promise.resolve({ data: 'signed-url' })),
        };

        const loginServiceMock = {
            getToken: jest.fn(() => Promise.resolve('token')),
        };

        const extensionSdkService = new ExtensionSdkService(httpClientMock, loginServiceMock);

        const result = await extensionSdkService.signIframeSrc(
            'TestApp',
            'http://localhost:7100/app-base/index.html?location-id=sw-main-hidden&privileges=%5B%5D',
        );

        expect(httpClientMock.post).toHaveBeenCalledWith(
            '/_action/extension-sdk/sign-uri',
            {
                appName: 'TestApp',
                uri: 'http://localhost:7100/app-base/index.html?location-id=sw-main-hidden&privileges=%5B%5D',
            },
            expect.any(Object),
        );

        expect(result).toBe('signed-url');
    });
});
