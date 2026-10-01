/**
 * @sw-package discovery
 */
import ThemeApiService from './theme.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

function createService(client = null, loginService = null) {
    if (!client) {
        client = createHTTPClient();
    }

    if (!loginService) {
        loginService = createLoginService(client, Shopware.Context.api);
    }

    return new ThemeApiService(client, loginService);
}

describe('ThemeApiService', () => {
    beforeEach(() => {
        Shopware.Store.get('session').languageId = 'en-GB';
    });

    it('assigns theme to a sales channel', async () => {
        const service = createService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({ data: { foo: 'bar' } });

        const result = await service.assignTheme('theme-id', 'sales-channel-id', { foo: 'bar' }, { baz: 'qux' });

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/theme/theme-id/assign/sales-channel-id',
            {},
            expect.objectContaining({
                params: { foo: 'bar' },
                headers: expect.objectContaining({ Authorization: expect.any(String) }),
            }),
        );
        expect(result).toEqual({ foo: 'bar' });
    });

    it('updates theme config via PATCH', async () => {
        const service = createService();
        const patchSpy = jest.spyOn(service.httpClient, 'patch').mockResolvedValue({ data: { success: true } });

        const result = await service.updateTheme('theme-id', { config: { foo: 'bar' } }, { reset: true });

        expect(patchSpy).toHaveBeenCalledWith(
            '/_action/theme/theme-id',
            { config: { foo: 'bar' } },
            expect.objectContaining({
                params: { reset: true },
                headers: expect.objectContaining({ Authorization: expect.any(String) }),
            }),
        );
        expect(result).toEqual({ success: true });
    });

    it('resets theme via PATCH', async () => {
        const service = createService();
        const patchSpy = jest.spyOn(service.httpClient, 'patch').mockResolvedValue({ data: { reset: true } });

        const result = await service.resetTheme('theme-id');

        expect(patchSpy).toHaveBeenCalledWith(
            '/_action/theme/theme-id/reset',
            {},
            expect.objectContaining({
                params: {},
                headers: expect.objectContaining({ Authorization: expect.any(String) }),
            }),
        );
        expect(result).toEqual({ reset: true });
    });

    it('loads configuration with language header', async () => {
        const service = createService();
        const getSpy = jest.spyOn(service.httpClient, 'get').mockResolvedValue({ data: { fields: {} } });

        const result = await service.getConfiguration('theme-id');

        expect(getSpy).toHaveBeenCalledWith(
            '/_action/theme/theme-id/configuration',
            expect.objectContaining({
                headers: expect.objectContaining({
                    Authorization: expect.any(String),
                    'sw-language-id': 'en-GB',
                }),
            }),
        );
        expect(result).toEqual({ fields: {} });
    });

    it('loads structured fields with language header', async () => {
        const service = createService();
        const getSpy = jest.spyOn(service.httpClient, 'get').mockResolvedValue({ data: { tabs: {} } });

        const result = await service.getStructuredFields('theme-id');

        expect(getSpy).toHaveBeenCalledWith(
            '/_action/theme/theme-id/structured-fields',
            expect.objectContaining({
                headers: expect.objectContaining({
                    Authorization: expect.any(String),
                    'sw-language-id': 'en-GB',
                }),
            }),
        );
        expect(result).toEqual({ tabs: {} });
    });

    it('validates fields with language header', async () => {
        const service = createService();
        const postSpy = jest.spyOn(service.httpClient, 'post').mockResolvedValue({ data: { valid: true } });

        const result = await service.validateFields({ foo: 'bar' });

        expect(postSpy).toHaveBeenCalledWith(
            '/_action/theme/validate-fields',
            { fields: { foo: 'bar' } },
            expect.objectContaining({
                headers: expect.objectContaining({
                    Authorization: expect.any(String),
                    'sw-language-id': 'en-GB',
                }),
            }),
        );
        expect(result).toEqual({ valid: true });
    });
});
