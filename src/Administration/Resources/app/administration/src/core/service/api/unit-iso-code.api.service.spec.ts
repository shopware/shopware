/**
 * @sw-package inventory
 */

import UnitIsoCodeApiService from 'src/core/service/api/unit-iso-code.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createUnitIsoCodeApiService() {
    const context = Shopware.Context.api;
    const client = createHTTPClient(context);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);
    const unitIsoCodeApiService = new UnitIsoCodeApiService(client, loginService);

    return { unitIsoCodeApiService, clientMock };
}

describe('core/service/api/unit-iso-code.api.service.ts', () => {
    it('should be registered as unitIsoCodeService', () => {
        const { unitIsoCodeApiService } = createUnitIsoCodeApiService();

        expect(unitIsoCodeApiService).toBeInstanceOf(UnitIsoCodeApiService);
        expect(unitIsoCodeApiService.name).toBe('unitIsoCodeService');
    });

    it('should load the unit codes', async () => {
        const { unitIsoCodeApiService, clientMock } = createUnitIsoCodeApiService();
        const isoCodes = [
            { code: 'H87', name: 'piece' },
            { code: 'KGM', name: 'kilogram' },
        ];
        clientMock.onGet('/_action/unit/iso-codes').reply(200, isoCodes);

        await expect(unitIsoCodeApiService.getIsoCodes()).resolves.toEqual(isoCodes);
    });
});
