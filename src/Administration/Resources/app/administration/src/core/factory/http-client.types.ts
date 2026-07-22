/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type HttpHeaders = Record<string, unknown>;

// The transport accepts and returns application-defined payloads.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type HttpClientValue = any;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface HttpRequestConfig<Data = HttpClientValue> {
    url?: string;
    method?: string;
    baseURL?: string;
    headers?: HttpHeaders;
    params?: HttpClientValue;
    data?: Data;
    timeout?: number;
    signal?: AbortSignal;
    cancelToken?: HttpCancelToken;
    responseType?: string;
    adapter?: unknown;
    useAxiosV1?: boolean;
    [key: string]: unknown;
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
export interface HttpCancelToken {
    promise: Promise<unknown>;
    reason?: unknown;
    throwIfRequested: () => void;
}

interface HttpCancelTokenSource {
    token: HttpCancelToken;
    cancel: (message?: string) => void;
}

interface HttpCancelTokenFactory {
    source: () => HttpCancelTokenSource;
}

interface HttpInterceptorManager<Value> {
    use: (
        onFulfilled?: (value: Value) => Value | Promise<Value>,
        onRejected?: (error: unknown) => unknown,
        options?: unknown,
    ) => number;
    eject: (id: number) => void;
    clear: () => void;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface HttpClient {
    <Data = HttpClientValue, Response = HttpResponse<Data>, RequestData = HttpClientValue>(
        config: HttpRequestConfig<RequestData>,
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
    getUri: (config?: HttpRequestConfig) => string;
    isCancel: (value: unknown) => boolean;
    CancelToken: HttpCancelTokenFactory;
    defaults: HttpRequestConfig;
    interceptors: {
        request: HttpInterceptorManager<HttpRequestConfig>;
        response: HttpInterceptorManager<HttpResponse>;
    };
}
