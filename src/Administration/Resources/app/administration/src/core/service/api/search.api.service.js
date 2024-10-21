import { CanceledError } from 'axios';
import ApiService from '../api.service';

/**
 * Gateway for the API end point 'search'
 * @class
 * @extends ApiService
 */
class SearchApiService extends ApiService {
    searchAbortController = null;

    constructor(httpClient, loginService, apiEndpoint = '_admin') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'searchService';
    }

    elastic(term, entities, limit, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        if (this.searchAbortController && !this.searchAbortController.signal.aborted) {
            this.searchAbortController.abort();
        }

        this.searchAbortController = new AbortController();

        return this.httpClient
            .post(
                `${this.getApiBasePath()}/es-search`,
                { term, limit, entities },
                {
                    headers,
                    signal: this.searchAbortController.signal,
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((error) => {
                if (error instanceof CanceledError) {
                    return {};
                }
                throw error;
            });
    }

    /**
     *
     * @param {object} queries
     * @param {object} additionalHeaders
     * */
    searchQuery(queries = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        Object.keys(queries).forEach((entity) => {
            if (typeof queries[entity].parse === 'function') {
                queries[entity] = queries[entity].parse();
            }
        });

        if (this.searchAbortController && !this.searchAbortController.signal.aborted) {
            this.searchAbortController.abort();
        }

        this.searchAbortController = new AbortController();

        return this.httpClient
            .post(`${this.getApiBasePath()}/search`, queries, {
                headers,
                signal: this.searchAbortController.signal,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((error) => {
                if (error instanceof CanceledError) {
                    return {};
                }
                throw error;
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default SearchApiService;
