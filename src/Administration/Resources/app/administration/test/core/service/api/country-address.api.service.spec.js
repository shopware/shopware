import CountryAddressApiService from 'src/core/service/api/country-address.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function getCountryAddressApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const countryAddressApiService = new CountryAddressApiService(client, loginService);
    return { countryAddressApiService, clientMock };
}

describe('documentService', () => {
    it('is registered correctly', () => {
        const { countryAddressApiService } = getCountryAddressApiService();
        expect(countryAddressApiService).toBeInstanceOf(CountryAddressApiService);
    });

    it('is rendered formatting address correctly', async () => {
        const { countryAddressApiService, clientMock } = getCountryAddressApiService();

        clientMock.onPost('/_action/country/formatting-address')
            .reply(
                200,
                'random-string',
            );

        const address = {
            country: {
                useDefaultAddressFormat: false,
                advancedAddressFormatPlain: 'random-format',
            },
        };

        const res = await countryAddressApiService.formattingAddress(address);
        expect(res).toEqual('random-string');
    });

    it('is not rendered formatting address when country uses default format', async () => {
        const { countryAddressApiService, clientMock } = getCountryAddressApiService();

        clientMock.onPost('/_action/country/formatting-address')
            .reply(
                200,
                'random-string',
            );

        const address = {
            country: {
                useDefaultAddressFormat: true,
                advancedAddressFormatPlain: 'random-format',
            },
        };

        const res = await countryAddressApiService.formattingAddress(address);
        expect(res).toEqual('');
    });

    it('is not rendered formatting address when country is not existed', async () => {
        const { countryAddressApiService, clientMock } = getCountryAddressApiService();

        clientMock.onPost('/_action/country/formatting-address')
            .reply(
                200,
                'random-string',
            );

        const address = {};

        const res = await countryAddressApiService.formattingAddress(address);
        expect(res).toEqual('');
    });

    it('is should show preview template', async () => {
        const { countryAddressApiService, clientMock } = getCountryAddressApiService();

        clientMock.onPost('/_action/country/formatting-address')
            .reply(
                200,
                'random-string',
            );

        const address = {
            country: {
                useDefaultAddressFormat: false,
                advancedAddressFormatPlain: 'random-format',
            },
        };

        const res = await countryAddressApiService.previewTemplate(address, 'random-format');
        expect(res).toEqual('random-string');
    });
});
