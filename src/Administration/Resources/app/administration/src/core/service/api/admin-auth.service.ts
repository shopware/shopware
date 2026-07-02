import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from 'src/core/service/login.service';

import ApiService from '../api.service';

/**
 * Result of the OIDC discovery action (`/_action/admin-auth/oidc/discover`).
 *
 * @private
 */
export type OidcDiscoveryResult = {
    issuer: string;
    authorizationEndpoint: string;
    tokenEndpoint: string;
    jwksUri: string;
    scopes: string[];
};

/**
 * A second factor the current user has enrolled.
 *
 * @private
 */
export type MfaMethod = {
    id: string;
    type: string;
    active: boolean;
    label: string | null;
    lastUsedAt: string | null;
    remaining?: number;
};

/**
 * @private
 */
export type TotpRegistrationOptions = {
    id: string;
    secret: string;
    otpauthUri: string;
};

/**
 * @private
 */
export type WebAuthnRegistrationOptions = {
    options: Record<string, unknown>;
    challengeToken: string;
};

/**
 * API service for the admin authentication endpoints (`/api/_action/admin-auth/*`):
 * OIDC provider discovery for the settings module and the MFA self-enrollment
 * endpoints used by the profile security tab.
 *
 * @class
 * @extends ApiService
 * @sw-package framework
 */
class AdminAuthApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'admin-auth') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'adminAuthService';
    }

    /**
     * Resolves the OIDC endpoints of a provider from its discovery document. The backend
     * accepts either the issuer URL or a full discovery URL.
     */
    async discoverOidc(payload: { discoveryUrl?: string; issuer?: string }): Promise<OidcDiscoveryResult> {
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/oidc/discover`, payload, {
                headers: this.getBasicHeaders(),
            })
            .then((response: AxiosResponse<OidcDiscoveryResult>) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Lists the second factors the current user has enrolled.
     */
    async getMfaMethods(): Promise<MfaMethod[]> {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/mfa/methods`, {
                headers: this.getBasicHeaders(),
            })
            .then((response: AxiosResponse<{ methods: MfaMethod[] }>) => {
                return ApiService.handleResponse(response);
            })
            .then((data: { methods: MfaMethod[] }) => data.methods);
    }

    /**
     * Begins a TOTP enrollment. The returned secret and otpauth URI are exposed exactly once.
     * Requires the `user-verified` scope (fresh password re-verification).
     */
    async totpRegisterOptions(label = '', additionalHeaders: Record<string, string> = {}): Promise<TotpRegistrationOptions> {
        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}/mfa/totp/register/options`,
                { label },
                { headers: this.getBasicHeaders(additionalHeaders) },
            )
            .then((response: AxiosResponse<TotpRegistrationOptions>) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Completes a TOTP enrollment by proving possession of the seed with a valid code.
     */
    async totpRegisterVerify(
        id: string,
        code: string,
        additionalHeaders: Record<string, string> = {},
    ): Promise<{ success: boolean }> {
        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}/mfa/totp/register/verify`,
                { id, code },
                { headers: this.getBasicHeaders(additionalHeaders) },
            )
            .then((response: AxiosResponse<{ success: boolean }>) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Begins a passkey enrollment: returns the WebAuthn creation options and the signed
     * challenge token the verify call must echo back.
     */
    async webauthnRegisterOptions(additionalHeaders: Record<string, string> = {}): Promise<WebAuthnRegistrationOptions> {
        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}/mfa/webauthn/register/options`,
                {},
                { headers: this.getBasicHeaders(additionalHeaders) },
            )
            .then((response: AxiosResponse<WebAuthnRegistrationOptions>) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Completes a passkey enrollment with the authenticator's attestation response.
     */
    async webauthnRegisterVerify(
        payload: { credential: string; challengeToken: string; label?: string },
        additionalHeaders: Record<string, string> = {},
    ): Promise<{ id: string; success: boolean }> {
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/mfa/webauthn/register/verify`, payload, {
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response: AxiosResponse<{ id: string; success: boolean }>) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * (Re-)generates the recovery-code set. The plaintext codes are returned exactly once;
     * regenerating invalidates all previous codes.
     */
    async generateRecoveryCodes(additionalHeaders: Record<string, string> = {}): Promise<string[]> {
        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}/mfa/recovery-codes`,
                {},
                { headers: this.getBasicHeaders(additionalHeaders) },
            )
            .then((response: AxiosResponse<{ codes: string[] }>) => {
                return ApiService.handleResponse(response);
            })
            .then((data: { codes: string[] }) => data.codes);
    }

    /**
     * Deletes one of the current user's own enrolled methods.
     */
    async deleteMfaMethod(id: string, additionalHeaders: Record<string, string> = {}): Promise<void> {
        return this.httpClient
            .delete(`/_action/${this.getApiBasePath()}/mfa/methods/${id}`, {
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response: AxiosResponse<void>) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default AdminAuthApiService;
