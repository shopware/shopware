/**
 * @sw-package framework
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

/**
 * Query parameters of an OAuth2 authorization code request as forwarded by the backend.
 *
 * @private
 */
export type OAuthAuthorizationParams = {
    response_type: string;
    client_id: string;
    redirect_uri: string;
    code_challenge: string;
    code_challenge_method: string;
    state?: string;
    scope?: string;
};

/**
 * @private
 */
export type OAuthAuthorizationInfo = {
    client: {
        id: string;
        name: string;
    };
    redirectUri: string;
    scopes: string[];
};

/**
 * @private
 */
export type OAuthAuthorizationDecision = {
    redirectUri: string;
};

/**
 * Gateway for the API end point "oauth/authorize" used by the consent page
 * of the OAuth2 authorization code flow.
 *
 * @private
 */
export default class OAuthAuthorizeApiService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'oauth/authorize') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'oauthAuthorizeApiService';
    }

    /**
     * Validates the authorization request and returns the client information to display on the consent page.
     */
    getInfo(params: OAuthAuthorizationParams): Promise<OAuthAuthorizationInfo> {
        return this.httpClient
            .get<OAuthAuthorizationInfo>(`${this.getApiBasePath()}/info`, {
                params,
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    /**
     * Submits the decision of the user. The returned redirect URI points back to the client
     * and contains either the authorization code or the OAuth error.
     */
    decide(params: OAuthAuthorizationParams, approved: boolean): Promise<OAuthAuthorizationDecision> {
        return this.httpClient
            .post<OAuthAuthorizationDecision>(
                this.getApiBasePath(),
                { ...params, approved },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => ApiService.handleResponse(response));
    }
}
