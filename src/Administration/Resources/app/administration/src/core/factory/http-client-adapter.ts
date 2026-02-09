/**
 * @sw-package framework
 *
 * @module core/factory/http-client-adapter
 */

import Axios from 'axios';
import type {
    AxiosInstance as AxiosInstanceV0,
    AxiosRequestConfig as AxiosRequestConfigV0,
    AxiosResponse as AxiosResponseV0,
} from 'axios';
import type {
    AxiosRequestConfig as AxiosRequestConfigV1,
    AxiosInstance as AxiosInstanceV1,
    AxiosResponse as AxiosResponseV1,
} from 'axios-v1';

type ResponseForConfig<TRequestConfig, TData = unknown> = TRequestConfig extends AxiosRequestConfigV1
    ? AxiosResponseV1<TData>
    : TRequestConfig extends AxiosRequestConfigV0
      ? AxiosResponseV0<TData>
      : AxiosResponseV0<TData> | AxiosResponseV1<TData>;
/**
 * Adapter interface for handling axios version-specific differences
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface HttpClientAdapter<TRequestConfig = AxiosRequestConfigV0 | AxiosRequestConfigV1> {
    /**
     * Execute a request using the underlying axios client
     */
    runRequest: <T = unknown>(config: TRequestConfig) => Promise<ResponseForConfig<TRequestConfig, T>>;

    /**
     * Check if an error is a cancellation error
     */
    isCancel: (value: unknown) => boolean;
}

/**
 * Creates an adapter for axios v1.x
 * Uses AbortController for request cancellation
 *
 * @param client - The axios v1 instance
 * @returns HttpClientAdapter for axios v1
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function createAxiosV1Adapter(client: AxiosInstanceV1): HttpClientAdapter<AxiosRequestConfigV1> {
    return {
        runRequest: <T = unknown>(config: AxiosRequestConfigV1): Promise<AxiosResponseV1<T>> => {
            return client.request<T>(config);
        },
        isCancel: (value: unknown): boolean => {
            // In axios v1, canceled requests throw errors with name 'CanceledError' or code 'ERR_CANCELED'
            if (value && typeof value === 'object') {
                const error = value as { name?: string; code?: string };
                return error.name === 'CanceledError' || error.code === 'ERR_CANCELED';
            }
            return false;
        },
    };
}

/**
 * Creates an adapter for axios v0.x
 * Uses CancelToken for request cancellation
 *
 * @param client - The axios v0 instance
 * @returns HttpClientAdapter for axios v0
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function createAxiosV0Adapter(client: AxiosInstanceV0): HttpClientAdapter<AxiosRequestConfigV0> {
    return {
        runRequest: <T = unknown>(config: AxiosRequestConfigV0): Promise<AxiosResponseV0<T>> => {
            return client.request<T>(config);
        },
        isCancel: (value: unknown): boolean => {
            // Use Axios.isCancel static method from axios v0
            return Axios.isCancel(value);
        },
    };
}
