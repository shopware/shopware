/**
 * @sw-package framework
 *
 * @module core/factory/http-legacy-compat
 *
 * Reproduces the observable behaviour of the removed legacy Axios 0.x transport on top of the single Axios 1.x
 * client, so `useAxiosV1: false` keeps working without shipping a second Axios copy.
 *
 * Only two behaviours actually differ between the Axios version Shopware shipped before and Axios 1.x:
 *
 * 1. Query parameter encoding. Axios 0.x decoded `[`, `]`, `:`, `$` and `,` back to their literal form after
 *    `encodeURIComponent`, Axios 1.x leaves them percent-encoded. Both are equivalent for a correct server, but
 *    signature checks, log greps and strict route matchers can depend on the literal form.
 * 2. Response headers. Axios 0.x returned a plain object, Axios 1.x returns an `AxiosHeaders` instance.
 *
 * Everything else (error class and codes, `FormData` handling, JSON parsing, `CancelToken`) is already identical.
 *
 * @deprecated tag:v6.9.0 - The legacy compatibility mode and the `useAxiosV1` request option will be removed.
 */

import Axios from 'axios';
import type { AxiosError, AxiosInstance, AxiosResponse, InternalAxiosRequestConfig } from 'axios';

/**
 * Characters that Axios 0.x decoded again after `encodeURIComponent`.
 */
const LEGACY_LITERAL_CHARACTERS: [RegExp, string][] = [
    [
        /%5B/gi,
        '[',
    ],
    [
        /%5D/gi,
        ']',
    ],
    [
        /%3A/gi,
        ':',
    ],
    [
        /%24/g,
        '$',
    ],
    [
        /%2C/gi,
        ',',
    ],
];

type LegacyParamsSerializerOptions = {
    indexes?: boolean | null;
    serialize?: unknown;
};

/**
 * Applies the Axios 0.x character map to an already serialized query string.
 *
 * @deprecated tag:v6.9.0 - Will be removed together with the legacy compatibility mode.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function toLegacyQueryString(serializedParams: string): string {
    return LEGACY_LITERAL_CHARACTERS.reduce(
        (
            params,
            [
                pattern,
                replacement,
            ],
        ) => params.replace(pattern, replacement),
        serializedParams,
    );
}

/**
 * Serializes request parameters the way the legacy Axios 0.x transport did.
 *
 * @deprecated tag:v6.9.0 - Will be removed together with the legacy compatibility mode.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function legacyParamsSerializer(params: unknown, options?: LegacyParamsSerializerOptions): string {
    const serialized = Axios.getUri({
        url: '',
        params,
        paramsSerializer: { indexes: options?.indexes, serialize: undefined },
    });

    return toLegacyQueryString(serialized.replace(/^\?/, ''));
}

/**
 * Resolves whether a request runs in legacy compatibility mode.
 *
 * The resolution order matches the one the removed dual-transport facade used:
 * an explicit `useAxiosV1` in the request config wins, otherwise the `V6_8_0_0` feature flag decides.
 *
 * @deprecated tag:v6.9.0 - Will be removed; Axios 1.x behaviour becomes unconditional.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function isLegacyCompatRequest(config: { useAxiosV1?: boolean }): boolean {
    return !(config.useAxiosV1 ?? Shopware?.Feature?.isActive('V6_8_0_0') ?? false);
}

/**
 * Converts the `AxiosHeaders` instance of a response back into the plain object Axios 0.x returned.
 */
function toPlainHeaders(response: AxiosResponse | undefined): void {
    if (!response?.headers || typeof response.headers !== 'object') {
        return;
    }

    // `Object.assign` rather than a spread because `AxiosHeaders` is iterable, and rather than its `toJSON()`
    // because that returns a null-prototype object while Axios 0.x returned an ordinary one.
    response.headers = Object.assign({}, response.headers) as AxiosResponse['headers'];
}

/**
 * Registers the interceptors that restore the legacy Axios 0.x behaviour for requests that opt into it.
 *
 * The request interceptor runs synchronously so the request pipeline keeps its synchronous fast path.
 *
 * @deprecated tag:v6.9.0 - Will be removed together with the legacy compatibility mode.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function legacyCompatInterceptor(client: AxiosInstance): AxiosInstance {
    client.interceptors.request.use(
        (config: InternalAxiosRequestConfig) => {
            // An explicit serializer always wins, exactly as it did with the legacy transport.
            if (isLegacyCompatRequest(config) && !config.paramsSerializer) {
                config.paramsSerializer = { serialize: legacyParamsSerializer };
            }

            return config;
        },
        null,
        { synchronous: true },
    );

    client.interceptors.response.use(
        (response: AxiosResponse) => {
            if (isLegacyCompatRequest(response.config ?? {})) {
                toPlainHeaders(response);
            }

            return response;
        },
        (error: AxiosError) => {
            if (isLegacyCompatRequest(error?.config ?? {})) {
                toPlainHeaders(error?.response);
            }

            return Promise.reject(error);
        },
    );

    return client;
}
