import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import SearchApiService from './search.api.service';

function getSearchApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const searchApiService = new SearchApiService(client, loginService);
    return { searchApiService, clientMock };
}

describe('searchApiService', () => {
    it('is registered correctly', async () => {
        const { searchApiService } = getSearchApiService();
        expect(searchApiService).toBeInstanceOf(SearchApiService);
    });

    it('is request elastic send correctly', async () => {
        const { searchApiService, clientMock } = getSearchApiService();

        clientMock.onPost('/_admin/es-search')
            .reply(
                200,
                { data: 'foo' },
            );

        const response = await searchApiService.elastic('bar', [], 10);

        expect(response.data).toBe('foo');
    });

    it('is request searchQuery send correctly', async () => {
        const { searchApiService, clientMock } = getSearchApiService();

        clientMock.onPost('/_admin/search')
            .reply(
                200,
                { data: 'foo' },
            );

        const response = await searchApiService.searchQuery({});

        expect(response.data).toBe('foo');
    });

    it('is request aborted correctly', async () => {
        const { searchApiService, clientMock } = getSearchApiService();

        clientMock.onPost('/_admin/search')
            .reply(
                200,
                { data: 'foo' },
            );

        const response = searchApiService.searchQuery({});
        searchApiService.searchAbortController.abort();

        expect(await response).toEqual({});
        expect(searchApiService.searchAbortController).toBeInstanceOf(AbortController);
        expect(searchApiService.searchAbortController.signal.aborted).toBeTruthy();
    });
});
