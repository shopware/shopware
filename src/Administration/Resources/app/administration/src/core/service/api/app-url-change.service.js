/**
 * @package admin
 */
import ApiService from '../api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class AppUrlChangeService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'appUrlChangeService';
    }

    /**
     * @returns {Promise<Array<{key: string, description: string}>>}
     */
    fetchResolverStrategies() {
        return this.httpClient
            .get('app-system/app-url-change/strategies', {
                headers: this.getBasicHeaders(),
            })
            .then(({ data }) => {
                return Object.entries(data).map(
                    ([
                        key,
                        description,
                    ]) => {
                        return { name: key, description };
                    },
                );
            });
    }

    /**
     * @param {{name: string}} strategy
     * @returns {*}
     */
    resolveUrlChange({ name }) {
        return this.httpClient.post(
            'app-system/app-url-change/resolve',
            { strategy: name },
            {
                headers: this.getBasicHeaders(),
            },
        );
    }

    /**
     * @returns {Promise<{newUrl: string, oldUrl: string} | null>}
     */
    getUrlDiff() {
        return this.httpClient
            .get('app-system/app-url-change/url-difference', {
                headers: this.getBasicHeaders(),
            })
            .then((resp) => {
                if (resp.status === 204) {
                    return null;
                }
                return resp.data;
            });
    }
}
