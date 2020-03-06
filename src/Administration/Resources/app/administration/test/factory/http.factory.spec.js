import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

const getClientMock = () => {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);

    return { client, clientMock };
};

describe('core/factory/http.factory.js', () => {
    beforeEach(() => {
        Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
        Shopware.Context.api.apiVersion = 1;
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

            clientMock[`on${method.charAt(0).toUpperCase() + method.slice(1)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should set the right base url with the default version (v2) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${method.charAt(0).toUpperCase() + method.slice(1)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v2');
            expect(response.config.version).toBeUndefined();
        });

        test(`should change the version per request (v2 to v1) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${method.charAt(0).toUpperCase() + method.slice(1)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', { version: 1 });
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should change the version per request (v1 to v2) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 1;

            const { client, clientMock } = getClientMock();

            clientMock[`on${method.charAt(0).toUpperCase() + method.slice(1)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', { version: 2 });
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v2');
            expect(response.config.version).toBeUndefined();
        });
    });

    ['post', 'put', 'patch'].forEach(method => {
        test(`should set the right base url with the default version (v1) with ${method}`, async () => {
            const { client, clientMock } = getClientMock();

            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 1;

            clientMock[`on${method.charAt(0).toUpperCase() + method.slice(1)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should set the right base url with the default version (v2) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${method.charAt(0).toUpperCase() + method.slice(1)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test');
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v2');
            expect(response.config.version).toBeUndefined();
        });

        test(`should change the version per request (v2 to v1) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 2;

            const { client, clientMock } = getClientMock();

            clientMock[`on${method.charAt(0).toUpperCase() + method.slice(1)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', null, { version: 1 });
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v1');
            expect(response.config.version).toBeUndefined();
        });

        test(`should change the version per request (v1 to v2) with ${method}`, async () => {
            Shopware.Context.api.apiPath = 'https://www.shopware-test.de/api';
            Shopware.Context.api.apiVersion = 1;

            const { client, clientMock } = getClientMock();

            clientMock[`on${method.charAt(0).toUpperCase() + method.slice(1)}`]('/test')
                .reply(200, { it: 'works' });

            const response = await client[method]('/test', null, { version: 2 });
            expect(response.config.baseURL).toEqual('https://www.shopware-test.de/api/v2');
            expect(response.config.version).toBeUndefined();
        });
    });
});
