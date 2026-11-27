/**
 * @sw-package framework
 */
import SyncService from 'src/core/service/api/sync.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function getSyncService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const syncService = new SyncService(client, loginService);
    return { syncService, clientMock };
}

describe('SyncService', () => {
    /**
     * Undefined values in sync requests wiped all custom fields.
     * @regression
     * @see https://github.com/shopware/shopware/issues/7131
     */
    it('sends undefined values correctly', async () => {
        const { syncService, clientMock } = getSyncService();
        let didRequest = false;

        clientMock
            .onPost('/_action/sync', {
                id: 'foo',
                customFields: {
                    bar: null,
                },
            })
            .reply(() => {
                didRequest = true;

                return [200, {}];
            });

        syncService.sync({
            id: 'foo',
            customFields: {
                bar: undefined,
            },
        });

        expect(didRequest).toBeTruthy();
    });
});
