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
     * @param autoNavigate - automatically reload page or navigate to redirectUrl (happens typically via sales channel switching) if provided
     */
    public async call(data: Record<string, unknown> = {}, autoNavigate = false): Promise<ContextTokenResponse> {
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

        const tokenResponse = await response.json() as ContextTokenResponse;

        if (!autoNavigate) {
            return tokenResponse;
        }

        if (tokenResponse.redirectUrl) {
            const currentUrl = new URL(window.location.href);
            const redirectBase = new URL(tokenResponse.redirectUrl);

            // Clean up paths and join properly
            const redirectPath = redirectBase.pathname.replace(/\/$/, '');
            const currentPath = currentUrl.pathname.replace(/^\/+/, '');

            const fullPath = `${redirectPath}/${currentPath}`;
            const finalUrl = new URL(fullPath + currentUrl.search + currentUrl.hash, redirectBase.origin);

            window.location.href = finalUrl.toString();
            return tokenResponse;
        }

        window.location.reload();
        return tokenResponse;
    }
}
