/**
 * @package admin
 */

import parseJsonApi from 'src/core/service/jsonapi-parser.service';
import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from './login.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type BasicHeaders = {
    Accept: string,
    Authorization: string,
    'Content-Type': string,
    [key: string]: string,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ApiResponse<T> = T extends (null|undefined) ? AxiosResponse<T> : T;

/**
 * ApiService class which provides the common methods for our REST API
 * @class
 */
class ApiService {
    client: AxiosInstance = {} as AxiosInstance;

    loginService: LoginService;

    endpoint = '';

    type = 'application/vnd.api+json';

    name = '';

    constructor(
        httpClient: AxiosInstance,
        loginService: LoginService,
        apiEndpoint: string,
        contentType = 'application/vnd.api+json',
    ) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.apiEndpoint = apiEndpoint;
        this.contentType = contentType;
    }

    /**
     * Returns the URI to the API endpoint
     */
    getApiBasePath(id?: string|number, prefix = ''): string {
        let url = '';

        if (prefix?.length) {
            url += `${prefix}/`;
        }

        if ((id && typeof id === 'number') || (typeof id === 'string' && id.length > 0)) {
            return `${url}${this.apiEndpoint}/${id}`;
        }

        return `${url}${this.apiEndpoint}`;
    }

    /**
     * Get the basic headers for a request.
     */
    getBasicHeaders(additionalHeaders = {}): BasicHeaders {
        let languageIdHeader = {};
        // eslint-disable-next-line no-restricted-globals
        if (self?.Shopware && typeof Shopware.Context?.api?.languageId === 'string') {
            languageIdHeader = {
                'sw-language-id': Shopware.Context.api.languageId,
            };
        }

        const basicHeaders = {
            Accept: this.contentType,
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
            ...languageIdHeader,
        };

        return { ...basicHeaders, ...additionalHeaders };
    }

    /**
     * Basic response handling.
     * Converts the JSON api data when the specific content type is set.
     */
    static handleResponse<T = unknown>(response: AxiosResponse<T>): ApiResponse<T> {
        if (response.data === null || response.data === undefined) {
            return response as ApiResponse<T>;
        }

        const headers = response.headers as {'content-type'? : string}|null|undefined;

        if (typeof headers === 'object' && headers !== null && headers['content-type'] === 'application/vnd.api+json') {
            return ApiService.parseJsonApiData<ApiResponse<T>>(response.data);
        }

        return response.data as ApiResponse<T>;
    }

    /**
     * Parses a JSON api data structure to a simplified object.
     */
    static parseJsonApiData<T = unknown>(data: string|object): T {
        return parseJsonApi(data) as T;
    }

    static getVersionHeader(versionId: string): { 'sw-version-id': string } {
        return { 'sw-version-id': versionId };
    }

    static makeQueryParams(paramDictionary = {} as { [key: string]: string|number}): string {
        const params = Object
            .keys(paramDictionary)
            .filter(key => typeof paramDictionary[key] === 'string')
            .map(key => `${key}=${paramDictionary[key]}`);

        if (!params.length) {
            return '';
        }

        return `?${params.join('&')}`;
    }

    /**
     * Getter for the API end point
     */
    get apiEndpoint(): string {
        return this.endpoint;
    }

    /**
     * Setter for the API end point
     */
    set apiEndpoint(endpoint: string) {
        this.endpoint = endpoint;
    }

    /**
     * Getter for the http client
     */
    get httpClient(): AxiosInstance {
        return this.client;
    }

    /**
     * Setter for the http client
     */
    set httpClient(client) {
        this.client = client;
    }

    get contentType(): string {
        return this.type;
    }

    set contentType(contentType) {
        this.type = contentType;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ApiService;
