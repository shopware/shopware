import MockAdapter from 'axios-mock-adapter';
import createLoginService from 'src/core/service/login.service';
import createHttpClient from 'src/core/factory/http.factory';
import UserConfigService from './user-config.api.service';

function newUserConfigService(client) {
    return new UserConfigService(client, createLoginService(client, Shopware.Context.api));
}

describe('userConfigService', () => {
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

        mockAdapter.onPost('/api/_info/config-me').replyOnce(204);

        await userConfigService.upsert({
            'core.userConfig': ['new-value'],
        });

        expect(mockAdapter.history.post).toHaveLength(1);
        expect(mockAdapter.history.post[0].data).toEqual(JSON.stringify({ 'core.userConfig': ['new-value'] }));
    });
});
