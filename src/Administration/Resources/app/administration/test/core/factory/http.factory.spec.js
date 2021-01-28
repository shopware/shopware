/* eslint-disable max-len */
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

    afterEach(() => {
        Shopware.Feature.isActive = () => false;
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
                        detail: 'The delete request for tax was denied due to a conflict.' +
                                'This entity is currently in use by: tax_rule (27)',
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
                title: 'global.default.error',
                message: 'global.notification.messageDeleteFailed<br>global.default.xTimesIn <b>global.entities.tax_rule</b>'
            });
        });

        test('should throw multiple errors for sync api requests which return multiple errors', async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 3;
            Shopware.Feature.isActive = (flag) => {
                return flag === 'FEATURE_NEXT_10539';
            };

            const { client, clientMock } = getClientMock();
            clientMock.onPost('/_action/sync')
                .reply(400, {
                    data: {
                        entityName: {
                            extensions: [],
                            result: [{
                                entities: [],
                                errors: [{
                                    status: '409',
                                    code: 'FRAMEWORK__DELETE_RESTRICTED',
                                    title: 'Conflict',
                                    detail: 'The delete request for tax was denied due to a conflict. This entity is currently in use by: tax_rule (27)',
                                    meta: {
                                        parameters: {
                                            entity: 'tax',
                                            usagesString: 'tax_rule (27)',
                                            usages: [{ entityName: 'tax_rule', count: 27 }]
                                        }
                                    }
                                }]
                            }, {
                                entities: [],
                                errors: [{
                                    status: '409',
                                    code: 'FRAMEWORK__DELETE_RESTRICTED',
                                    title: 'Conflict',
                                    detail: 'The delete request for tax was denied due to a conflict. This entity is currently in use by: product_price (20)',
                                    meta: {
                                        parameters: {
                                            entity: 'rule',
                                            usagesString: 'product_price (20)',
                                            usages: [{ entityName: 'product_price', count: 20 }]
                                        }
                                    }
                                }]
                            }, {
                                // Should skip entities with empty errors
                                entities: [],
                                errors: []
                            }, {
                                // Should skip nested 400
                                entities: [],
                                errors: [{
                                    status: '400',
                                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                                    detail: 'This value should not be blank.',
                                    meta: {
                                        parameters: {}
                                    }
                                }]
                            }]
                        },
                        anotherEntity: {
                            extensions: [],
                            result: [{
                                entities: [],
                                errors: [{
                                    status: '409',
                                    code: 'FRAMEWORK__DELETE_RESTRICTED',
                                    title: 'Conflict',
                                    detail: 'The delete request for tax was denied due to a conflict. This entity is currently in use by: tax_rule (27)',
                                    meta: {
                                        parameters: {
                                            entity: 'tax',
                                            usagesString: 'random_entity (55)',
                                            usages: [{ entityName: 'random_entity', count: 55 }]
                                        }
                                    }
                                }]
                            }]
                        }
                    }
                });

            const dispatchSpy = jest.fn();

            Object.defineProperty(Shopware.State, 'dispatch', {
                value: dispatchSpy
            });
            try {
                await client.post('/_action/sync');
            } catch (e) {
                expect(e.response.status).toBe(400);
            }
            expect(dispatchSpy).toHaveBeenCalledTimes(3);
            expect(dispatchSpy.mock.calls).toEqual([
                ['notification/createNotification', {
                    variant: 'error',
                    title: 'global.default.error',
                    message: 'global.notification.messageDeleteFailed<br>global.default.xTimesIn <b>global.entities.tax_rule</b>'
                }],
                ['notification/createNotification', {
                    variant: 'error',
                    title: 'global.default.error',
                    message: 'global.notification.messageDeleteFailed<br>global.default.xTimesIn <b>global.entities.product_price</b>'
                }],
                ['notification/createNotification', {
                    variant: 'error',
                    title: 'global.default.error',
                    message: 'global.notification.messageDeleteFailed<br>global.default.xTimesIn <b>global.entities.random_entity</b>'
                }]
            ]);
        });
    });
});
