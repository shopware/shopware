import ApiService from '../api.service';

/**
 * @class
 * @internal
 * @extends ApiService
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class SsoInvitationService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'api') {
        super(httpClient, loginService, apiEndpoint, 'application/json');
        this.name = 'ssoInvitationService';
    }

    inviteUser(email, localeId) {
        return this.httpClient.post(
            '/_action/sso/invite-user',
            {
                email: email,
                localeId: localeId,
            },
            { headers: this.getBasicHeaders() },
        );
    }
}
