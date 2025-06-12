/**
 * @internal
 *
 * @sw-package after-sales
 */
import MockAdapter from "axios-mock-adapter";
import SaasSettingsService from "./saas-settings.service";
import createHTTPClient from "../../factory/http.factory";
import createLoginService from "../login.service";

function createSaasSettingService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);

    const loginService = createLoginService(clientMock, Shopware.Context.api);

    const service = new SaasSettingsService(client, loginService);

    return {
        service,
        clientMock
    };
}

describe('core/service/api/saas-settings.service.js', () => {
    it('should return isSaas info with true value', async () => {
        const {service, clientMock} = createSaasSettingService();

        clientMock.onGet('/api/_info/is-saas').reply(200, {
                    isSaas: true
            });

        const isSaas = await service.isSaas().then((response) => {
            return response;
        });

        expect(isSaas.isSaas).toBeTruthy();
    })

    it('should return isSaas info with false value', async () => {
        const {service, clientMock} = createSaasSettingService();

        clientMock.onGet('/api/_info/is-saas').reply(200, {
            isSaas: false
        });

        const isSaas = await service.isSaas().then((response) => {
            return response;
        });

        expect(isSaas.isSaas).toBeFalsy();
    })
});
