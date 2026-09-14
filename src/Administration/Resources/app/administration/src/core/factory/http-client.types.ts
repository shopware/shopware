/**
 * @sw-package framework
 */

// The transport accepts and returns application-defined payloads.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type HttpClientValue = any;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type HttpHeaders = Record<string, HttpClientValue>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface HttpRequestConfig<Data = HttpClientValue> {
    [key: string]: HttpClientValue;
    url?: string;
    method?: string;
    baseURL?: string;
    headers?: HttpClientValue;
    params?: HttpClientValue;
    data?: Data;
    timeout?: number;
    signal?: HttpClientValue;
    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use `signal` with an `AbortController` instead.
     */
    cancelToken?: HttpClientValue;
    responseType?: 'arraybuffer' | 'blob' | 'document' | 'json' | 'text' | 'stream' | 'formdata';
    adapter?: HttpClientValue;
    version?: number;
    /**
     * @deprecated tag:v6.8.0 - Has no effect anymore and will be removed. The client only ships Axios 1.x.
     */
    useAxiosV1?: boolean;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface HttpResponse<Data = HttpClientValue> {
    data: Data;
    status: number;
    statusText: string;
    // Transport-specific response metadata deliberately stays loose so consumers do not depend on Axios types.
    headers: HttpClientValue;
    config: HttpClientValue;
    request?: HttpClientValue;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface HttpError<Data = HttpClientValue> extends Error {
    code?: string;
    config?: HttpRequestConfig;
    request?: HttpClientValue;
    response?: HttpResponse<Data>;
    status?: number;
}

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use `AbortController` and `AbortSignal` instead.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface HttpCancelToken {
    promise: Promise<unknown>;
    reason?: unknown;
    throwIfRequested: () => void;
}

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use `AbortController` instead.
 */
interface HttpCancelTokenSource {
    token: HttpCancelToken;
    cancel: (message?: string) => void;
}

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use `AbortController` instead.
 */
interface HttpCancelTokenFactory {
    new (executor: (cancel: (message?: string) => void) => void): HttpCancelToken;
    source: () => HttpCancelTokenSource;
}

interface HttpInterceptorManager<Value> {
    handlers: HttpClientValue[];
    use: <Result = Value>(
        onFulfilled?: ((value: Value) => Result | Promise<Result>) | null,
        onRejected?: ((error: HttpClientValue) => HttpClientValue) | null,
        options?: unknown,
    ) => number;
    eject: (id: number) => void;
    clear: () => void;
    forEach: (callback: (handler: HttpClientValue) => void) => void;
}

interface HttpClientDefaults extends HttpRequestConfig {
    headers: HttpHeaders & {
        common: HttpHeaders;
        delete: HttpHeaders;
        get: HttpHeaders;
        head: HttpHeaders;
        post: HttpHeaders;
        put: HttpHeaders;
        patch: HttpHeaders;
    };
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface HttpClient {
    <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        config: HttpRequestConfig<RequestData>,
    ): Promise<Response>;
    <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        config?: HttpRequestConfig<RequestData>,
    ): Promise<Response>;
    request: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        config: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    get: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    delete: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    head: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    options: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    post: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        data?: RequestData,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    put: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        data?: RequestData,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    patch: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        data?: RequestData,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    postForm: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        data?: RequestData,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    putForm: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        data?: RequestData,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    patchForm: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        data?: RequestData,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    query: <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        url: string,
        data?: RequestData,
        config?: HttpRequestConfig<RequestData>,
    ) => Promise<Response>;
    /**
     * Creates a derived client that inherits this client's defaults. Keeps the facade structurally compatible with
     * `AxiosInstance`, so extensions can still hand the client to `axios-mock-adapter` without a cast.
     */
    create: (config?: HttpRequestConfig) => HttpClient;
    getUri: (config?: HttpRequestConfig) => string;
    isCancel: (value: unknown) => boolean;
    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use `AbortController` and the `signal` request option instead.
     */
    CancelToken: HttpCancelTokenFactory;
    defaults: HttpClientDefaults;
    interceptors: {
        // Request interceptors always receive the resolved request config, which carries the headers.
        request: HttpInterceptorManager<HttpRequestConfig & { headers: HttpClientValue }>;
        response: HttpInterceptorManager<HttpResponse>;
    };
}
