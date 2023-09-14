import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import UsageDataApiService from 'src/core/service/api/usage-data.api.service';

function getUsageDataService(client) {
    return new UsageDataApiService(client, createLoginService(client, Shopware.Context.api));
}

describe('metricsService', () => {
    const client = createHTTPClient();
    const service = getUsageDataService(client);

    it('has the correct name', async () => {
        expect(service.name).toBe('usageDataService');
    });

    describe('needs approval request', () => {
        it('is defined', async () => {
            expect(service.needsApproval).toBeDefined();
        });

        it('calls the correct endpoint', async () => {
            const getMethod = jest.spyOn(client, 'get').mockImplementation(() => Promise.resolve({
                data: false,
            }));

            service.needsApproval();
            expect(getMethod).toHaveBeenCalledTimes(1);
            expect(getMethod).toHaveBeenCalledWith('/usage-data/needs-approval', {
                headers: service.getBasicHeaders(),
                params: {},
            });
        });
    });
});
