/**
 * @sw-package framework
 */

import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';
import createLoginService from 'src/core/service/login.service';
import ContentSystemElementTypeApiService from './content-system-element-type.api.service';

describe('contentSystemElementTypeService', () => {
    const endpoint = '/_info/content-system-element-types.json';
    let clientMock: MockAdapter;
    let service: ContentSystemElementTypeApiService;

    beforeEach(() => {
        const context = Shopware.Context.api;
        const client = createHTTPClient(context);
        clientMock = new MockAdapter(client);
        service = new ContentSystemElementTypeApiService(client, createLoginService(client, context));
    });

    afterEach(() => clientMock.restore());

    it('returns the element types and the ordered breakpoint fallbacks from the info endpoint', async () => {
        const payload = { types: [{ name: 'Sw:Grid:Container' }], breakpointFallbacks: { xs: ['sm', 'lg'] } };
        clientMock.onGet(endpoint).reply(200, payload);

        await expect(service.getMetadata()).resolves.toEqual(payload);
        await expect(service.getTypes()).resolves.toEqual(payload.types);
    });

    it('uses an empty fallback map when the endpoint returns only types', async () => {
        const types = [{ name: 'Sw:Grid:Container' }];
        clientMock.onGet(endpoint).reply(200, { types });

        await expect(service.getMetadata()).resolves.toEqual({ types, breakpointFallbacks: {} });
    });

    it('uses an empty type list when the response has no type array', async () => {
        clientMock.onGet(endpoint).reply(200, { types: null, breakpointFallbacks: { xxl: ['xl', 'lg'] } });

        await expect(service.getMetadata()).resolves.toEqual({ types: [], breakpointFallbacks: { xxl: ['xl', 'lg'] } });
    });
});
