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
    cancelToken?: HttpClientValue;
    responseType?: 'arraybuffer' | 'blob' | 'document' | 'json' | 'text' | 'stream';
    adapter?: HttpClientValue;
    version?: number;
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
    new (executor: (cancel: (message?: string) => void) => void): HttpCancelToken;
    source: () => HttpCancelTokenSource;
}

interface HttpInterceptorManager<Value> {
    handlers: HttpClientValue[];
    use: <Result = Value>(
        onFulfilled?: (value: Value) => Result | Promise<Result>,
        onRejected?: (error: HttpClientValue) => HttpClientValue,
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
    getUri: (config?: HttpRequestConfig) => string;
    isCancel: (value: unknown) => boolean;
    CancelToken: HttpCancelTokenFactory;
    defaults: HttpClientDefaults;
    interceptors: {
        request: HttpInterceptorManager<HttpRequestConfig>;
        response: HttpInterceptorManager<HttpResponse>;
    };
}
