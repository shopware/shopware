/**
 * @package data-services
 */
import MockAdapter from 'axios-mock-adapter';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import UsageDataApiService from 'src/core/service/api/usage-data.api.service';

function getUsageDataService(client) {
    return new UsageDataApiService(client, createLoginService(client, Shopware.Context.api));
}

describe('usageDataService', () => {
    it('has the correct name', async () => {
        const usageDataApiService = getUsageDataService(createHTTPClient());

        expect(usageDataApiService.name).toBe('usageDataService');
    });

    it('gets the consent from api', async () => {
        const client = createHTTPClient();
        const mockAdapter = new MockAdapter(client);
        const usageDataApi = getUsageDataService(client);

        mockAdapter.onGet('/api/usage-data/consent').reply(200, {
            isConsentGiven: false,
            isBannerHidden: false,
        });

        const response = await usageDataApi.getConsent();

        expect(response).toEqual({
            isConsentGiven: false,
            isBannerHidden: false,
        });
    });

    it('sends the acceptance of the consent', async () => {
        const client = createHTTPClient();
        const mockAdapter = new MockAdapter(client);
        const usageDataApi = getUsageDataService(client);

        mockAdapter.onPost('/api/usage-data/accept-consent').reply(204);

        expect(await usageDataApi.acceptConsent()).toBeUndefined();
    });

    it('sends the revocation of the consent', async () => {
        const client = createHTTPClient();
        const mockAdapter = new MockAdapter(client);
        const usageDataApi = getUsageDataService(client);

        mockAdapter.onPost('/api/usage-data/revoke-consent').reply(204);

        expect(await usageDataApi.revokeConsent()).toBeUndefined();
    });

    it('can send a hide request for the dashboard banner', async () => {
        const client = createHTTPClient();
        const mockAdapter = new MockAdapter(client);
        const usageDataApi = getUsageDataService(client);

        mockAdapter.onPost('/api/usage-data/hide-consent-banner').reply(204);

        expect(await usageDataApi.hideBanner()).toBeUndefined();
    });
});
