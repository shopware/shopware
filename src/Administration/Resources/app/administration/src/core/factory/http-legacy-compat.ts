/**
 * @sw-package framework
 *
 * @module core/factory/http-legacy-compat
 *
 * Everything the single Axios 1.x client keeps for extensions written against the removed Axios 0.x transport:
 * the legacy compatibility mode selected by `useAxiosV1`, the `CancelToken` shim and the version-specific aliases.
 * Removing it means deleting this file, its call and the `CancelToken` export in `http.factory.js`, and the
 * declarations tagged `v6.8.0` in `http-client.types.ts` and `global.types.ts`.
 *
 * @deprecated tag:v6.8.0 - Will be removed together with the `useAxiosV1` request option.
 */

import Axios from 'axios';
import type {
    AxiosError,
    AxiosInstance,
    AxiosResponse,
    CustomParamsSerializer,
    InternalAxiosRequestConfig,
    ParamsSerializerOptions,
} from 'axios';

type LegacyConfig = {
    useAxiosV1?: boolean;
    cancelToken?: unknown;
    paramsSerializer?: ParamsSerializerOptions | CustomParamsSerializer;
};

const REMOVED_IN = 'V6_8_0_0';

/**
 * Axios 0.x encoded query keys and values like Axios 1.x does, but kept `[` and `]` literal.
 */
function legacyEncode(value: string): string {
    return encodeURIComponent(value)
        .replace(/%3A/gi, ':')
        .replace(/%24/g, '$')
        .replace(/%2C/gi, ',')
        .replace(/%20/g, '+')
        .replace(/%5B/gi, '[')
        .replace(/%5D/gi, ']');
}

function isLegacy(config: LegacyConfig | undefined): boolean {
    return !(config?.useAxiosV1 ?? Shopware.Feature.isActive('V6_8_0_0'));
}

function guardLegacyOptions(config: LegacyConfig): void {
    if (config.useAxiosV1 !== undefined) {
        Shopware.Feature.triggerDeprecationOrThrow(
            REMOVED_IN,
            'The "useAxiosV1" request option is deprecated. Remove it once the request works with Axios 1.x.',
        );
    }

    if (config.cancelToken) {
        triggerCancelTokenDeprecation();
    }
}

function triggerCancelTokenDeprecation(): void {
    Shopware.Feature.triggerDeprecationOrThrow(
        REMOVED_IN,
        '"httpClient.CancelToken" and the "cancelToken" request option are deprecated. Pass the "signal" of an AbortController instead.',
    );
}

/**
 * An own `serialize` function still wins, and serializer options such as `indexes` keep the legacy encoding.
 */
function withLegacyParams<Config extends LegacyConfig>(config: Config): Config {
    if (!isLegacy(config)) {
        return config;
    }

    const own =
        typeof config.paramsSerializer === 'function' ? { serialize: config.paramsSerializer } : config.paramsSerializer;

    return { ...config, paramsSerializer: { encode: legacyEncode, ...own } };
}

function toPlainHeaders(response: AxiosResponse | undefined): void {
    if (response && isLegacy(response.config)) {
        // Not `toJSON()`, which returns a null-prototype object while Axios 0.x returned an ordinary one.
        response.headers = Object.assign({}, response.headers) as AxiosResponse['headers'];
    }
}

function registerDeprecatedMembers(client: AxiosInstance): void {
    const aliases = {
        axiosV0: client,
        axiosV1: client,
        interceptorsV0: client.interceptors,
        interceptorsV1: client.interceptors,
        defaultsV0: client.defaults,
        defaultsV1: client.defaults,
    };

    Object.entries(aliases).forEach(([name, target]) => {
        Object.defineProperty(client, name, {
            get() {
                Shopware.Feature.triggerDeprecationOrThrow(
                    REMOVED_IN,
                    `"httpClient.${name}" is deprecated. Use "httpClient", "httpClient.interceptors" or "httpClient.defaults" instead.`,
                );

                return target;
            },
        });
    });

    Object.defineProperty(client, 'CancelToken', {
        get() {
            triggerCancelTokenDeprecation();

            return Axios.CancelToken;
        },
    });
}

/**
 * Services that took a `CancelToken` before now take an `AbortSignal` in the same parameter and pass it on as
 * `signal`, so a `CancelToken` can still arrive there.
 */
function moveCancelTokenOutOfSignal(config: InternalAxiosRequestConfig & LegacyConfig): void {
    const { signal } = config;

    if (isCancelToken(signal)) {
        config.cancelToken = signal;
        delete config.signal;
    }
}

function isCancelToken(value: unknown): value is NonNullable<InternalAxiosRequestConfig['cancelToken']> {
    return typeof value === 'object' && value !== null && 'throwIfRequested' in value;
}

/**
 * Registers the legacy compatibility mode and the deprecated members on the HTTP client.
 *
 * @private
 */
export default function registerLegacyCompat(client: AxiosInstance): void {
    client.interceptors.request.use(
        (config: InternalAxiosRequestConfig & LegacyConfig) => {
            moveCancelTokenOutOfSignal(config);
            guardLegacyOptions(config);

            return Object.assign(config, withLegacyParams(config));
        },
        null,
        // Production also registers asynchronous interceptors, but specs without them expect the request to reach
        // the adapter in the same tick.
        { synchronous: true },
    );

    client.interceptors.response.use(
        (response: AxiosResponse) => {
            toPlainHeaders(response);

            return response;
        },
        (error: AxiosError) => {
            toPlainHeaders(error?.response);

            return Promise.reject(error);
        },
    );

    // Interceptors do not run for `getUri`, so it applies the query encoding on its own.
    const getUri = client.getUri.bind(client);
    client.getUri = (config = {}) => {
        guardLegacyOptions(config);

        return getUri(withLegacyParams(config));
    };

    registerDeprecatedMembers(client);
}
