/**
 * @sw-package framework
 */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from 'src/core/service/api.service';
import type { SystemHealthCheck } from '../types';

/**
 * @private
 */
export default class SystemHealthApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService) {
        super(httpClient, loginService, '_admin/system-health', 'application/json');
        this.name = 'systemHealthApiService';
    }

    getChecks(): Promise<SystemHealthCheck[]> {
        return this.httpClient
            .get('_admin/system-health', { headers: this.getBasicHeaders() })
            .then((response) => (response.data as { checks: SystemHealthCheck[] }).checks);
    }

    /**
     * Queues all checks, or the one with the given name. The results arrive in getChecks() once a worker ran them.
     */
    run(name: string | null = null): Promise<void> {
        return this.httpClient
            .post('_admin/system-health/run', null, {
                headers: this.getBasicHeaders(),
                params: name ? { check: name } : {},
            })
            .then(() => undefined);
    }
}
