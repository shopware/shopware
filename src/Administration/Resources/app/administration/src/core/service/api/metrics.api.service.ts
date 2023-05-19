import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * Gateway for the API endpoint "metrics"
 *
 * @private
 *
 * @package merchant-services
 */
export default class MetricsApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'metrics') {
        super(httpClient, loginService, apiEndpoint, 'application/json');

        this.name = 'metricsService';
    }

    public async needsApproval(): Promise<boolean> {
        const headers = this.getBasicHeaders();
        const params = {};

        const { data } = await this.httpClient.get<boolean>(
            `/${this.getApiBasePath()}/needs-approval`,
            { params, headers },
        );

        return data;
    }
}

const METRICS_SYSTEM_CONFIG_DOMAIN = 'core.metrics';
const ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY = 'core.metrics.shareUsageData';

/**
 * @private
 *
 * @package merchant-services
 */
export { METRICS_SYSTEM_CONFIG_DOMAIN, ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY };

/**
 * @private
 * @package merchant-services
 */
export type { MetricsApiService };
