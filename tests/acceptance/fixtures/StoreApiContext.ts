import { request, APIResponse, APIRequestContext } from '@playwright/test';

export interface StoreApiOptions {
    app_url?: string;
    'sw-access-key'?: string;
    'sw-context-token'?: string;
    ignoreHTTPSErrors?: boolean;
}

interface Options<PAYLOAD> {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    [key: string]: any;
    data?: PAYLOAD;
}

export class StoreApiContext {
    private context: APIRequestContext;
    public options: StoreApiOptions;

    constructor(context: APIRequestContext, options: StoreApiOptions) {
        this.context = context;
        this.options = options;
    }

    public static async newContext(options: StoreApiOptions) {
        return new StoreApiContext(await this.createContext(options), options);
    }

    static async createContext(options: StoreApiOptions) {
        const extraHTTPHeaders = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'sw-access-key': options['sw-access-key'],
        };

        if (options['sw-context-token']) {
            extraHTTPHeaders['sw-context-token'] = options['sw-context-token'];
        }

        return await request.newContext({
            baseURL: `${options['app_url']}store-api/`,
            ignoreHTTPSErrors: options.ignoreHTTPSErrors,
            extraHTTPHeaders,
        });
    }

    async login(storeUser) {
        const loginResponse = await this.post(`account/login`, {
            data: {
                username: storeUser.email,
                password: storeUser.password,
            },
        });

        const responseHeaders = loginResponse.headers();

        if (!responseHeaders['sw-context-token']) {
            throw new Error(`Failed to login with user ${storeUser.email} | ${JSON.stringify(loginResponse)}`);
        }

        this.options['sw-context-token'] = responseHeaders['sw-context-token'];
        this.context = await StoreApiContext.createContext(this.options);

        return responseHeaders;
    }

    async delete<PAYLOAD>(url: string, options?: PAYLOAD): Promise<APIResponse> {
        return this.context.delete(url, options);
    }

    async get<PAYLOAD>(url: string, options?: Options<PAYLOAD>): Promise<APIResponse> {
        return this.context.get(url, options);
    }

    async post<PAYLOAD>(url: string, options?: Options<PAYLOAD>): Promise<APIResponse> {
        return this.context.post(url, options);
    }

    async fetch<PAYLOAD>(url: string, options?: Options<PAYLOAD>): Promise<APIResponse> {
        return this.context.fetch(url, options);
    }

    async head<PAYLOAD>(url: string, options?: Options<PAYLOAD>): Promise<APIResponse> {
        return this.context.head(url, options);
    }
}
