/**
 * @sw-package inventory
 */

import UnitUneceCodeApiService from 'src/core/service/api/unit-unece-code.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createUnitUneceCodeApiService() {
    const context = Shopware.Context.api;
    const client = createHTTPClient(context);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);
    const unitUneceCodeApiService = new UnitUneceCodeApiService(client, loginService);

    return { unitUneceCodeApiService, clientMock };
}

describe('core/service/api/unit-unece-code.api.service.ts', () => {
    it('should be registered as unitUneceCodeService', () => {
        const { unitUneceCodeApiService } = createUnitUneceCodeApiService();

        expect(unitUneceCodeApiService).toBeInstanceOf(UnitUneceCodeApiService);
        expect(unitUneceCodeApiService.name).toBe('unitUneceCodeService');
    });

    it('should load the unit codes', async () => {
        const { unitUneceCodeApiService, clientMock } = createUnitUneceCodeApiService();
        const uneceCodes = [
            { code: 'H87', name: 'piece' },
            { code: 'KGM', name: 'kilogram' },
        ];
        clientMock.onGet('/_action/unit/unece-codes').reply(200, uneceCodes);

        await expect(unitUneceCodeApiService.getUneceCodes()).resolves.toEqual(uneceCodes);
    });
});
