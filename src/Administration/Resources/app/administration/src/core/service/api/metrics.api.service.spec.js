import MetricsApiService from 'src/core/service/api/metrics.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

function getMetricsService(client) {
    return new MetricsApiService(client, createLoginService(client, Shopware.Context.api));
}

describe('metricsService', () => {
    const client = createHTTPClient();
    const service = getMetricsService(client);

    it('has the correct name', async () => {
        expect(service.name).toBe('metricsService');
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
            expect(getMethod).toHaveBeenCalledWith('/metrics/needs-approval', {
                headers: service.getBasicHeaders(),
                params: {},
            });
        });
    });
});
