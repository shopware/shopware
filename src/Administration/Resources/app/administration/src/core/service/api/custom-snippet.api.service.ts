import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

interface Country {
    name: string;
    translated?: {
        name: string;
    };
}

interface CountryState {
    name: string;
    translated?: {
        name: string;
    };
}

interface Salutation {
    displayName: string;
    translated?: {
        displayName: string;
    };
}

interface Address {
    salutation?: Salutation;
    title?: string;
    firstName: string;
    lastName: string;
    street: string;
    zipcode?: string;
    city: string;
    country: Country;
    countryState?: CountryState;
    company?: string;
    phoneNumber?: string;
    department?: string;
    additionalAddressLine1?: string;
    additionalAddressLine2?: string;
}

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class CustomSnippetApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, 'custom-snippet', 'application/json');

        this.name = 'customSnippetApiService';
    }

    snippets(): Promise<unknown> {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response: AxiosResponse<Array<string[]>>) => {
                return ApiService.handleResponse(response);
            });
    }

    render(address: Address, format: Array<string[]>): Promise<unknown> {
        const params = { data: { address }, format };

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/render`, params, {
                headers: this.getBasicHeaders(),
            })
            .then((response: AxiosResponse<string>) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { Address };
