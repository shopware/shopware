/**
 * @sw-package framework
 */
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import SystemHealthApiService from './system-health.api.service';

function createService() {
    const context = Shopware.Context?.api || {};
    const client = createHTTPClient(context);
    const mock = new MockAdapter(client);
    const service = new SystemHealthApiService(client, createLoginService(client, context));

    return { service, mock };
}

describe('module/sw-settings-system-health/service/system-health.api.service', () => {
    it('should load the checks', async () => {
        const { service, mock } = createService();
        const checks = [{ name: 'AppUrlVerification', category: 'EXTERNAL', result: null }];
        mock.onGet('/_info/system-check/results').reply(200, { checks });

        expect(await service.getChecks()).toEqual(checks);
    });

    it('should queue all checks without a name', async () => {
        const { service, mock } = createService();
        mock.onPost('/_action/system-check/run').reply(202);

        await service.run();

        expect(mock.history.post[0].params).toEqual({});
    });

    it('should queue one check by its name', async () => {
        const { service, mock } = createService();
        mock.onPost('/_action/system-check/run').reply(202);

        await service.run('AppUrlVerification');

        expect(mock.history.post[0].params).toEqual({ check: 'AppUrlVerification' });
    });
});
