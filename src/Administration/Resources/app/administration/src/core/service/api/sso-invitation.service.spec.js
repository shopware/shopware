/**
 * @internal
 *
 * @sw-package framework
 */
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from '../../factory/http.factory';
import createLoginService from '../login.service';
import SsoInvitationService from './sso-invitation.service';

function createSsoInvitationService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);

    const loginService = createLoginService(clientMock, Shopware.Context.api);

    const service = new SsoInvitationService(client, loginService);

    return {
        service,
        clientMock,
    };
}

describe('core/service/api/sso-invitation.service.js', () => {
    it('invite user should be successfully', async () => {
        const { service, clientMock } = createSsoInvitationService();

        clientMock
            .onPost('/api/_action/sso/invite-user', {
                email: 'test@example.com',
                localeId: 'anyLocaleId',
            })
            .reply(200);

        const result = await service.inviteUser('test@example.com', 'anyLocaleId');

        expect(result.status).toBe(200);
    });
});
