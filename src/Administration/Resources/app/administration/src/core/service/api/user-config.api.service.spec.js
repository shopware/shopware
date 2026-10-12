import MockAdapter from 'axios-mock-adapter';
import createLoginService from 'src/core/service/login.service';
import createHttpClient from 'src/core/factory/http.factory';
import CacheService from 'src/app/service/cache.service';
import UserConfigService from './user-config.api.service';

function newUserConfigService(client) {
    return new UserConfigService(client, createLoginService(client, Shopware.Context.api));
}

if (!Shopware.Service('cacheService')) {
    Shopware.Service().register('cacheService', () => new CacheService());
}

describe('userConfigService', () => {
    beforeEach(() => {
        Shopware.Service('cacheService').clear();
    });

    it('has the correct name', async () => {
        const userConfigService = newUserConfigService(createHttpClient());

        expect(userConfigService.name).toBe('userConfigService');
    });

    it('fetches user configs from the API', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.userConfig': ['some-value'],
            },
        });

        const response = await userConfigService.search();

        expect(response).toEqual({
            data: {
                'core.userConfig': ['some-value'],
            },
        });
    });

    it('filters requested keys from the cached response', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.userConfig': ['some-value'],
                'core.otherConfig': ['other-value'],
            },
        });

        const response = await userConfigService.search(['core.otherConfig']);

        expect(response).toEqual({
            data: {
                'core.otherConfig': ['other-value'],
            },
        });
    });

    it('reuses the cached full response until forced to reload', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.userConfig': ['some-value'],
            },
        });

        await userConfigService.search(['core.userConfig']);
        await userConfigService.search(['core.userConfig']);

        expect(mockAdapter.history.get).toHaveLength(1);
    });

    it('returns undefined on error and logs the error', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(503);
        const consoleSpy = jest.spyOn(Shopware.Utils.debug, 'error').mockImplementation(() => {});

        const response = await userConfigService.search();

        expect(consoleSpy).toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalledWith('UserConfigService', expect.anything());
        expect(response).toBeUndefined();
    });

    it('sends upsert request for user configs', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.userConfig': ['old-value'],
            },
        });
        mockAdapter.onPatch('/api/_info/config-me').replyOnce(204);
        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.userConfig': ['new-value'],
            },
        });

        await userConfigService.search(['core.userConfig']);

        await userConfigService.upsert({
            'core.userConfig': ['new-value'],
        });

        const response = await userConfigService.search(['core.userConfig']);

        expect(mockAdapter.history.patch).toHaveLength(1);
        expect(mockAdapter.history.patch[0].data).toEqual(JSON.stringify({ 'core.userConfig': ['new-value'] }));
        expect(mockAdapter.history.get).toHaveLength(2);
        expect(response).toEqual({
            data: {
                'core.userConfig': ['new-value'],
            },
        });
    });
});
