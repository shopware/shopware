/**
 * @sw-package fundamentals@discovery
 */
import TranslationApiService from 'src/core/service/api/translation.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createTranslationApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const translationApiService = new TranslationApiService(client, loginService);
    return { translationApiService, clientMock };
}

describe('translationApiService', () => {
    it('is registered under the name translationService', () => {
        const { translationApiService } = createTranslationApiService();

        expect(translationApiService.name).toBe('translationService');
    });

    it('requests the translation list', async () => {
        const { translationApiService, clientMock } = createTranslationApiService();
        const payload = { total: 1, items: [{ locale: 'de-DE', name: 'German', progress: 100 }] };

        clientMock.onGet('/api/_action/translation/list').reply(200, payload);

        const result = await translationApiService.getList();

        expect(result).toEqual(payload);
    });

    it('installs the given locales', async () => {
        const { translationApiService, clientMock } = createTranslationApiService();

        clientMock
            .onPost('/api/_action/translation/install', {
                locales: ['de-DE'],
                all: false,
                activate: true,
            })
            .reply(200, { success: true });

        const result = await translationApiService.install({ locales: ['de-DE'] });

        expect(result).toEqual({ success: true });
    });

    it('installs all locales when requested', async () => {
        const { translationApiService, clientMock } = createTranslationApiService();

        clientMock
            .onPost('/api/_action/translation/install', {
                locales: [],
                all: true,
                activate: false,
            })
            .reply(200, { success: true });

        const result = await translationApiService.install({ all: true, activate: false });

        expect(result).toEqual({ success: true });
    });

    it('updates all installed translations', async () => {
        const { translationApiService, clientMock } = createTranslationApiService();

        clientMock.onPost('/api/_action/translation/update', {}).reply(200, { success: true });

        const result = await translationApiService.update();

        expect(result).toEqual({ success: true });
    });

    it('deletes the translation of a single locale', async () => {
        const { translationApiService, clientMock } = createTranslationApiService();

        clientMock.onDelete('/api/_action/translation/de-DE').reply(204);

        await translationApiService.deleteTranslation('de-DE');

        expect(clientMock.history.delete).toHaveLength(1);
        expect(clientMock.history.delete[0].url).toBe('/_action/translation/de-DE');
    });
});
