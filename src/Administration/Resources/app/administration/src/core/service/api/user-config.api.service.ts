import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

type CacheServiceContract = {
    query: <T>(options: { key: unknown[]; fn: () => Promise<T>; ttl?: number; forceReload?: boolean }) => Promise<T>;
    invalidateCaches: (options: { cacheKey: unknown[] }) => void;
};

type SessionUser = {
    id?: string;
};

/**
 * Gateway for the API end point 'user-config'
 * @sw-package fundamentals@framework
 * @private
 */
export default class UserConfigService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = '_info/config-me') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userConfigService';
    }

    /**
     * @description Process search user-config based on provide array keys of user-config,
     * if keys is null, get all config of current logged-in user
     */
    search(keys: string[] | null = null, forceReload = false) {
        const currentUser = Shopware.Store.get('session').currentUser as SessionUser | undefined;
        const currentUserId = currentUser?.id ?? 'anonymous';
        const cacheService = Shopware.Service('cacheService') as CacheServiceContract;
        const headers = this.getBasicHeaders();

        return cacheService
            .query<Record<string, unknown>>({
                key: [
                    'user-config',
                    currentUserId,
                ],
                forceReload,
                fn: () =>
                    this.httpClient
                        .get<{ data: Record<string, unknown> }>(this.getApiBasePath(), {
                            params: { keys: null },
                            headers,
                        })
                        .then((response) => {
                            return ApiService.handleResponse(response).data ?? {};
                        }),
            })
            .then((response) => {
                if (keys === null) {
                    return {
                        data: response,
                    };
                }

                const filteredResponse = keys.reduce<Record<string, unknown>>((accumulator, key) => {
                    if (Object.hasOwn(response, key)) {
                        accumulator[key] = response[key];
                    }

                    return accumulator;
                }, {});

                return {
                    data: filteredResponse,
                };
            })
            .catch((error) => {
                Shopware.Utils.debug.error('UserConfigService', error);
            });
    }

    /**
     * @description Process mass upsert user-config for current logged-in user
     */
    upsert(upsertData: Record<string, unknown>): Promise<void> {
        const headers = this.getBasicHeaders();
        const currentUser = Shopware.Store.get('session').currentUser as SessionUser | undefined;
        const currentUserId = currentUser?.id ?? 'anonymous';

        return this.httpClient.patch<void>(this.getApiBasePath(), upsertData, { headers }).then((response) => {
            (Shopware.Service('cacheService') as CacheServiceContract).invalidateCaches({
                cacheKey: [
                    'user-config',
                    currentUserId,
                ],
            });

            return ApiService.handleResponse(response);
        });
    }
}
