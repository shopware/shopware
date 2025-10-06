import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import type { SnippetRegistry } from 'src/core/factory/locale.factory';
import type LocaleFactory from 'src/core/factory/locale.factory';

import ApiService from '../api.service';

type SnippetFilter = {
    total: number;
    data: Array<string>;
};

/**
 * @class
 * @extends ApiService
 * @sw-package discovery
 */
class SnippetApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'snippet') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'snippetService';
    }

    async getFilter(): Promise<SnippetFilter> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/filter`, {
                headers,
            })
            .then((response: AxiosResponse<SnippetFilter>) => {
                return ApiService.handleResponse(response);
            });
    }

    async getSnippets(localeFactory: typeof LocaleFactory, code: string | null = null): Promise<void> {
        const headers = this.getBasicHeaders();
        const locale = code || localeFactory.getLastKnownLocale();

        return this.httpClient
            .get(`/_admin/snippets?locale=${locale}`, {
                headers,
            })
            .then((response: AxiosResponse<SnippetRegistry>) => {
                return ApiService.handleResponse(response);
            })
            .then((snippetRegistry: SnippetRegistry) => {
                const registry = localeFactory.getLocaleRegistry();

                Object.entries(snippetRegistry).forEach(
                    ([
                        localeKey,
                        snippets,
                    ]) => {
                        const fnName = registry.has(localeKey) ? 'extend' : 'register';

                        localeFactory[fnName](localeKey, snippets);
                    },
                );
            });
    }

    async getLocales(): Promise<Array<string>> {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_admin/locales`, {
                headers,
            })
            .then((response: AxiosResponse<Array<string>>) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SnippetApiService;
