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
        // reload the page to apply context changes if no target is specified
        if (!customTarget && !tokenResponse.redirectUrl) {
            window.location.reload();
            return tokenResponse;
        }

        // otherwise redirect to the redirectUrl, which can be overridden by a customTarget path
        const currentUrl = new URL(window.location.href);
        const targetUrl = new URL(
            customTarget ?? currentUrl.pathname.replace(/^\//, '') ?? '',
            (tokenResponse.redirectUrl ?? currentUrl.href).replace(/\/$/, '') + '/',
        );

        targetUrl.search = currentUrl.search;
        targetUrl.hash = currentUrl.hash;

        window.location.href = targetUrl.toString().replace(/\/$/, '');

        return tokenResponse;
    }
}
