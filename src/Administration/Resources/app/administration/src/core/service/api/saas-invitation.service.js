import ApiService from "../api.service";

/**
 * @class
 * @internal
 * @extends ApiService
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class SaasInvitationService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'api') {
        super(httpClient, loginService, apiEndpoint, 'application/json');
        this.name = 'saasInvitationService';
    }

    inviteUser(email, localeId) {
        return this.httpClient.post(
            '/_action/saas/invite-user',
            {
                email: email,
                localeId: localeId,
            },
            { headers: this.getBasicHeaders() },
        );
    }
}
