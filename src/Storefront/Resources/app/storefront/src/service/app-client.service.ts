/**
 * @package storefront
 */
export default class AppClientService {
    private readonly name: string;
    constructor(name: string) {
        this.name = name;
    }

    get(url: RequestInfo, options: RequestInit = {}) {
        options.method = 'GET';

        return this.request(url, options);
    }

    post(url: RequestInfo, options: RequestInit = {}) {
        options.method = 'POST';

        return this.request(url, options);
    }

    patch(url: RequestInfo, options: RequestInit = {}) {
        options.method = 'PATCH';

        return this.request(url, options);
    }

    delete(url: RequestInfo, options: RequestInit = {}) {
        options.method = 'DELETE';

        return this.request(url, options);
    }

    /**
     * Resets the token for the current app. This will force the next request to fetch a new token.
     */
    reset(): void {
        window.sessionStorage.removeItem(this.getStorageKey());
    }

    // @ts-ignore
    private getStorageKey() {
        return `app-system.${this.name}`;
    }

    private async getHeaders() {
        const key = this.getStorageKey();
        if (!window.sessionStorage.getItem(key)) {
            const data = await this.fetchHeaders();

            window.sessionStorage.setItem(key, JSON.stringify(data));

            return data.headers;
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const data = JSON.parse(window.sessionStorage.getItem(key));

        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument,@typescript-eslint/no-unsafe-member-access
        if (new Date(data.expires) < new Date()) {
            window.sessionStorage.removeItem(key);

            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return await this.getHeaders();
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-member-access
        return data.headers;
    }

    private async fetchHeaders(): Promise<{ headers: { [key: string]: string }, expires: string }> {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
        const url = window['router']['frontend.app-system.generate-token'].replace('Placeholder', encodeURIComponent(this.name));
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        const response = await fetch(url, {
            method: 'POST',
        });

        if (!response.ok) {
            throw new Error(`Error while fetching token, got status code: ${response.status} with response ${await response.text()}`);
        }

        const data = await response.json() as { token: string, shopId: string, expires: string };

        return {
            headers: {
                'shopware-app-token': data.token,
                'shopware-app-shop-id': data.shopId,
            },
            expires: data.expires,
        };
    }

    private async request(url: RequestInfo, options: RequestInit) {
        if (!options.headers) {
            options.headers = {};
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        options.headers = {...options.headers, ...await this.getHeaders()};

        return fetch(url, options);
    }
}
