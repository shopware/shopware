import MockAdapter from 'axios-mock-adapter';
import SystemConfigService from 'src/core/service/api/system-config.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

function getApiServiceAndMockAdapter() {
    const client = createHTTPClient();
    const mockAdapter = new MockAdapter(client);

    const loginService = createLoginService(client, Shopware.Context.api);

    const apiService = new SystemConfigService(client, loginService);

    return {
        apiService,
        mockAdapter
    };
}

describe('system-config.api.service', () => {
    let systemConfigService = null;
    let axiosMock = null;

    beforeEach(() => {
        const { mockAdapter, apiService } = getApiServiceAndMockAdapter();

        systemConfigService = apiService;
        axiosMock = mockAdapter;
    });

    it('returns the config from api', async () => {
        axiosMock.onGet(
            '_action/system-config',
            {
                params: {
                    salesChannelId: null,
                    domain: 'system-config.domain'
                }
            }
        )
            .reply(200, {
                'system-config.value.text': 'some-text-value',
                'system-config.value.bool': true
            });

        const values = await systemConfigService.getValues('system-config.domain', null);

        expect(values).toEqual({
            'system-config.value.text': 'some-text-value',
            'system-config.value.bool': true
        });
    });

    it('always return an plain object', async () => {
        axiosMock.onGet(
            '_action/system-config',
            {
                params: {
                    salesChannelId: null,
                    domain: 'system-config.domain'
                }
            }
        )
            .reply(200, []);

        const values = await systemConfigService.getValues('system-config.domain', null);

        expect(Array.isArray(values)).toBe(false);
        expect(values).toEqual({});
    });
});
