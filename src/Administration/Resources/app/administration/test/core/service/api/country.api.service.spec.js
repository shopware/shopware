import CountryApiService from 'src/core/service/api/country.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createCustomSnippetApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const countryApiService = new CountryApiService(client, loginService);
    return { countryApiService, clientMock };
}

describe('addressFormattingApiService', () => {
    it('is registered correctly', () => {
        const { countryApiService } = createCustomSnippetApiService();

        expect(countryApiService).toBeInstanceOf(CountryApiService);
    });

    it('get address format used correctly', async () => {
        const { countryApiService, clientMock } = createCustomSnippetApiService();

        clientMock.onGet('/_info/country/address/default-format').reply(
            200,
            {
                data: [
                    [{ type: 'plain', value: '-' }]
                ]
            }
        );

        const { data } = await countryApiService.defaultCountryAddressFormat();

        expect(data).toEqual([[{ type: 'plain', value: '-' }]]);
    });
});
