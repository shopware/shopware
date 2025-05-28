interface ContextTokenResponse {
    token: string;
    redirectUrl?: string;
}

/**
 * @sw-package framework
 */
export default class ContextGatewayClient {
    private readonly name: string;

    constructor(name: string) {
        this.name = name;
    }

    /**
     * Calls the context gateway to apply context changes triggered by app servers
     *
     * @param data - custom data sent to the app server
     */
    public async call(data: Record<string, unknown> = {}): Promise<ContextTokenResponse> {
        const body = { ...data, appName: this.name };

        // @ts-ignore
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        const gatewayRoute = window['router']['frontend.gateway.context'] as string;
        const response = await fetch(gatewayRoute, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            const err = await response.text();
            throw new Error(`Context gateway request failed for app '${this.name}': ${response.status} ${response.statusText} - ${err}`);
        }

        return await response.json() as ContextTokenResponse;
    }

    public navigate(tokenResponse: ContextTokenResponse, customTarget: string | null = null): ContextTokenResponse {
        if (tokenResponse.redirectUrl) {
            const currentUrl = new URL(window.location.href);
            const redirectBase = new URL(tokenResponse.redirectUrl);

            redirectBase.pathname += customTarget ?? currentUrl.pathname;
            redirectBase.search = currentUrl.search;
            redirectBase.hash = currentUrl.hash;

            if (redirectBase.pathname.endsWith('/')) {
                redirectBase.pathname = redirectBase.pathname.slice(0, -1);
            }

            window.location.href = redirectBase.toString();

            return tokenResponse;
        }

        if (customTarget !== null) {
            const customUrl = new URL(customTarget, window.location.href);
            window.location.href = customUrl.toString();

            return tokenResponse;
        }

        window.location.reload();

        return tokenResponse;
    }
}
