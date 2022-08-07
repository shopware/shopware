import ApiService from '../api.service';

/**
 * Gateway for the API end point "country"
 * @class
 * @extends ApiService
 */
class CountryAddressApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'country') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'countryAddressService';
    }

    formattingAddress(address) {
        if (!address?.country || address.country.useDefaultAddressFormat) {
            return Promise.resolve('');
        }

        return this.previewTemplate(address, address.country.advancedAddressFormatPlain);
    }

    previewTemplate(addressData, addressFormat) {
        const apiRoute = `/_action/${this.getApiBasePath()}/formatting-address`;

        return this.httpClient.post(
            apiRoute,
            {
                addressData,
                addressFormat,
            },
            {
                headers: this.getBasicHeaders(),
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CountryAddressApiService;
