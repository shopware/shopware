import ApiService from 'src/core/service/api.service';

describe('/core/service/api.service.ts', () => {
    function createApiService(config) {
        return new ApiService(config.httpClient, config.loginService, config.apiEndpoint, config.contentType);
    }

    function createDefaultConfig() {
        return {
            httpClient: {
                get: jest.fn(),
                post: jest.fn(),
                patch: jest.fn(),
                delete: jest.fn(),
            },
            loginService: {
                getToken: jest.fn(() => {
                    return 'verySecureToken';
                }),
            },
            apiEndpoint: 'https://foo.bar/api',
            contentType: 'application/json',
        };
    }

    it('should return the basic headers', async () => {
        const languageId = Shopware.Context?.api?.languageId;
        Shopware.Context.api.languageId = null;

        const apiService = createApiService(createDefaultConfig());
        const headers = apiService.getBasicHeaders();

        expect(headers).toEqual({
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: 'Bearer verySecureToken',
        });

        Shopware.Context.api.languageId = languageId;
    });

    it('should return the basic headers with languageId', async () => {
        const languageId = Shopware.Context?.api?.languageId;
        Shopware.Context.api.languageId = '123456789';

        const apiService = createApiService(createDefaultConfig());
        const headers = apiService.getBasicHeaders();

        expect(headers).toEqual({
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: 'Bearer verySecureToken',
            'sw-language-id': '123456789',
        });

        Shopware.Context.api.languageId = languageId;
    });
});
