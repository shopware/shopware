import { request, APIResponse, APIRequestContext } from '@playwright/test';

export interface AppAuthOptions {
    app_url?: string;
    client_id?: string;
    client_secret?: string;
    access_token?: string;
    admin_username?: string;
    admin_password?: string;
    ignoreHTTPSErrors?: boolean;
}

interface Options<PAYLOAD> {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    [key: string]: any;
    data?: PAYLOAD;
}

export class AdminApiContext {
    private context: APIRequestContext;
    private readonly options: AppAuthOptions;

    constructor(context: APIRequestContext, options: AppAuthOptions) {
        this.context = context;
        this.options = options;
    }

    public static async newContext(options?: AppAuthOptions): Promise<AdminApiContext> {
        const withDefaults = options || {};

        withDefaults.app_url = withDefaults.app_url || process.env['APP_URL'];
        withDefaults.client_id = withDefaults.client_id || process.env['SHOPWARE_ACCESS_KEY_ID'];
        withDefaults.client_secret = withDefaults.client_secret || process.env['SHOPWARE_SECRET_ACCESS_KEY'];
        withDefaults.admin_username = withDefaults.admin_username || process.env['SHOPWARE_ADMIN_USERNAME'];
        withDefaults.admin_password = withDefaults.admin_password || process.env['SHOPWARE_ADMIN_PASSWORD'];
        withDefaults.ignoreHTTPSErrors = true;

        if (!withDefaults.client_id) {
            withDefaults.access_token = await this.authenticateWithPassword(withDefaults);
            const tmpContext = new AdminApiContext(await this.createContext(withDefaults), withDefaults);
            const accessKeyData = await (await tmpContext.get('./_action/access-key/intergration')).json() as { accessKey: string, secretAccessKey: string};

            const integrationData = {
                admin: true,
                label: 'Playwright acceptance test suite',
                ...accessKeyData,
            };
            await tmpContext.post('./integration', {
                data: integrationData,
            });
            withDefaults.client_id = accessKeyData.accessKey;
            withDefaults.client_secret = accessKeyData.secretAccessKey;
        }

        withDefaults.access_token = await this.authenticateWithClientCredentials(withDefaults);

        return new AdminApiContext(await this.createContext(withDefaults), withDefaults);
    }

    static async createContext(options: AppAuthOptions): Promise<APIRequestContext> {
        const extraHTTPHeaders = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        };

        if (options.access_token) {
            extraHTTPHeaders['Authorization'] = 'Bearer ' + options.access_token;
        }
        return await request.newContext({
            baseURL: `${options.app_url}api/`,
            ignoreHTTPSErrors: options.ignoreHTTPSErrors,
            extraHTTPHeaders,
        });
    }

    static async authenticateWithPassword(options: AppAuthOptions): Promise<string> {
        const authResponse: APIResponse = await (
            await this.createContext(options)
        ).post('./oauth/token', {
            data: {
                client_id: 'administration',
                grant_type: 'password',
                username: options.admin_username,
                password: options.admin_password,
                scope: ['write'],
            },
        });

        const authData = (await authResponse.json()) as { access_token?: string };

        if (!authData['access_token']) {
            throw new Error(
                'Failed to authenticate with SHOPWARE_ADMIN_USERNAME' +
                    options.client_id +
                    'Request: ' +
                    JSON.stringify({
                        grant_type: 'administration',
                        username: options.admin_username,
                    }) +
                    'Error: ' +
                    JSON.stringify(authData)
            );
        }

        return authData['access_token'];
    }

    static async authenticateWithClientCredentials(options: AppAuthOptions): Promise<string> {
        const authResponse: APIResponse = await (
            await this.createContext(options)
        ).post('./oauth/token', {
            data: {
                grant_type: 'client_credentials',
                client_id: options.client_id,
                client_secret: options.client_secret,
                scope: ['write'],
            },
        });

        const authData = (await authResponse.json()) as { access_token?: string }; 

        if (!authData['access_token']) {
            throw new Error(
                'Failed to authenticate with client_id' +
                    options.client_id +
                    'Request: ' +
                    JSON.stringify({
                        grant_type: 'client_credentials',
                        client_id: options.client_id,
                        client_secret: options.client_secret,
                    }) +
                    'Error: ' +
                    JSON.stringify(authData)
            );
        }

        return authData['access_token'];
    }

    isAuthenticated(): boolean {
        // TODO: check token expiry
        // best method would be to store the client time along side the token and diff that

        return !!this.options.access_token;
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

    async patch<PAYLOAD>(url: string, options?: Options<PAYLOAD>): Promise<APIResponse> {
        return this.context.patch(url, options);
    }
}
