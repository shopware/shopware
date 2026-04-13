import MockAdapter from 'axios-mock-adapter';
import ConsentApiService from './consent.api.service';
import createHTTPClient from '../factory/http.factory';
import createLoginService from '../service/login.service';

function setUpService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const consentApiService = new ConsentApiService(client, loginService);

    return { consentApiService, clientMock };
}

const defaultConsents = {
    test_consent: {
        name: 'test_consent',
        identifier: 'user-id',
        scopeName: 'user_id',
        status: 'unset',
        actor: null,
        updatedAt: null,
        acceptedRevision: null,
        latestRevision: null,
    },
};

describe('core/consent/consent.api.service', () => {
    it('returns a list of consent objects', async () => {
        const { consentApiService, clientMock } = setUpService();

        clientMock.onGet('/consents').reply(200, {
            ...defaultConsents,
        });

        const { data } = await consentApiService.list();

        expect(data).toEqual(defaultConsents);
    });

    it('accept returns updated consent', async () => {
        const { consentApiService, clientMock } = setUpService();

        clientMock.onPost('/consents/accept', { consent: 'test_consent', revision: '2026-02-02' }).reply(200, {
            ...defaultConsents.test_consent,
        });

        const { data } = await consentApiService.accept('test_consent', '2026-02-02');

        expect(data).toEqual(defaultConsents.test_consent);
    });

    it('revoke returns updated consent', async () => {
        const { consentApiService, clientMock } = setUpService();

        clientMock.onPost('/consents/revoke', { consent: 'test_consent' }).reply(200, {
            ...defaultConsents.test_consent,
        });

        const { data } = await consentApiService.revoke('test_consent');

        expect(data).toEqual(defaultConsents.test_consent);
    });
});
