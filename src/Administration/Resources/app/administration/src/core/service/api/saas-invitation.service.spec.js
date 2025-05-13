/**
 * @internal
 *
 * @sw-package after-sales
 */
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from '../../factory/http.factory';
import createLoginService from '../login.service';
import SaasInvitationService from './saas-invitation.service';

function createSaasInvitationService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);

    const loginService = createLoginService(clientMock, Shopware.Context.api);

    const service = new SaasInvitationService(client, loginService);

    return {
        service,
        clientMock,
    };
}

describe('core/service/api/saas-invitation.service.js', () => {
    it('should be successfully', async () => {
        const { service, clientMock } = createSaasInvitationService();

        clientMock.onPost('/api/_action/saas/invite-user', {
            email: 'test@example.com',
            localeId: 'anyLocaleId',
        }).reply(200)

        const result = await service.inviteUser('test@example.com', 'anyLocaleId');

        expect(result.status).toBe(200);
    });
});
