import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

const getClientMock = () => {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);

    return { client, clientMock };
};

const firstLetterToUppercase = (value) => value.charAt(0).toUpperCase() + value.slice(1);

describe('core/factory/http.factory.js', () => {
    beforeEach(() => {
        Shopware.Utils.debug.warn = jest.fn();
        Shopware.Utils.debug.warn.mockClear();
        Shopware.Utils.debug.error = jest.fn();
        Shopware.Utils.debug.error.mockClear();
        Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
        Shopware.Context.api.apiVersion = 1;
        Shopware.Application.view = {
            root: {
                $tc: v => v
            }
        };
    });

    ['request', 'get', 'delete', 'head', 'options', 'post', 'put', 'patch'].forEach(method => {
        test(`should contain the method ${method}`, () => {
            const client = createHTTPClient();

            expect(client).toHaveProperty(method);
        });
    });

    ['get', 'delete', 'head', 'options'].forEach(method => {
        test(`should set the right base url with the default version (v1) with ${method}`, async () => {
            const { client, clientMock } = getClientMock();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 1;

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should set the right base url with the default version (v3) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v2');
            expect(response.config.version).toBeUndefined();
        });

        test(`should set the right base url with the default version (v2) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should change the version per request (v2 to v1) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', { version: 1 });
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should change the version per request (v1 to v2) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 1;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', { version: 2 });
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v2');
            expect(response.config.version).toBeUndefined();
        });

        test(`should throw an warning if version is deprecated with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).toHaveBeenCalled();
        });

        test(`should throw an warning if version is deprecated with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).toHaveBeenCalled();
        });

        test(`should not throw an warning if config version is not deprecated with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', { version: 3 });
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();
        });

        test(`should throw an warning if config version is deprecated with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', { version: 2 });
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).toHaveBeenCalled();
        });

        test(`should use current api version if version <= 0 is provided with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', { version: 0 });
            expect(response.config.url).toEqual(
                `${Shopware.Context.api.apiPath}/v${Shopware.Context.api.apiVersion - 1}/test`
            );
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).toHaveBeenCalled();
        });
    });

    ['post', 'put', 'patch'].forEach(method => {
        test(`should set the right base url with the default version (v1) with ${method}`, async () => {
            const { client, clientMock } = getClientMock();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 1;

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should set the right base url with the default version (v2) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should set the right base url with the default version (v3) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v2');
            expect(response.config.version).toBeUndefined();
        });

        test(`should change the version per request (v2 to v1) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', null, { version: 1 });
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should change the version per request (v1 to v2) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 1;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', null, { version: 2 });
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v2');
            expect(response.config.version).toBeUndefined();
        });

        test(`should throw an warning if version is deprecated with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).toHaveBeenCalled();
        });

        test(`should throw an warning if version is deprecated with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).toHaveBeenCalled();
        });

        test(`should not throw an warning if config version is not deprecated with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', null, { version: 3 });
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();
        });

        test(`should throw an warning if config version is deprecated with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', null, { version: 2 });
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).toHaveBeenCalled();
        });

        test(`should use current api version if version <= 0 is provided with method ${method}`, async () => {
            expect(Shopware.Utils.debug.warn).not.toHaveBeenCalled();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();

            clientMock[`on${firstLetterToUppercase(method)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', { version: 0 });
            expect(response.config.url).toEqual(
                `${Shopware.Context.api.apiPath}/v${Shopware.Context.api.apiVersion - 1}/test`
            );
            expect(response.status).toEqual(200);

            expect(Shopware.Utils.debug.warn).toHaveBeenCalled();
        });

        test('should throw a DELETE_RESTRICTED 409 error notification', async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;

            const { client, clientMock } = getClientMock();
            clientMock.onPost('/test')
                .reply(409, {
                    errors: [{
                        status: '409',
                        code: 'FRAMEWORK__DELETE_RESTRICTED',
                        title: 'Conflict',
                        detail: 'The delete request for tax was denied due to a conflict. The entity is currently in use by: tax_rule (27)',
                        meta: {
                            parameters: {
                                entity: 'tax',
                                usagesString: 'tax_rule (27)',
                                usages: ['tax_rule (27)']
                            }
                        }
                    }]
                });

            const dispatchSpy = jest.fn();

            Object.defineProperty(Shopware.State, 'dispatch', {
                value: dispatchSpy
            });
            try {
                await client.post('/test');
            } catch (e) {
                expect(e.response.status).toBe(409);
            }
            expect(dispatchSpy).toHaveBeenCalledWith('notification/createNotification', {
                variant: 'error',
                system: false,
                autoClose: false,
                growl: true,
                title: 'global.default.error',
                message: '\"Tax\" global.notification.messageDeleteFailed<br>tax_rule (27)'
            });
        });
    });
});
