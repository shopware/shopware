import CustomSnippetApiService from 'src/core/service/api/custom-snippet.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

/**
 * @package customer-order
 */

function createCustomSnippetApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const customSnippetApiService = new CustomSnippetApiService(client, loginService);
    return { customSnippetApiService, clientMock };
}

describe('addressFormattingApiService', () => {
    it('is registered correctly', () => {
        const { customSnippetApiService } = createCustomSnippetApiService();

        expect(customSnippetApiService).toBeInstanceOf(CustomSnippetApiService);
    });

    it('get snippets used correctly', async () => {
        const { customSnippetApiService, clientMock } = createCustomSnippetApiService();

        clientMock.onGet('/_action/custom-snippet').reply(
            200,
            {
                data: [
                    { type: 'plain', value: '-' },
                ],
            },
        );

        const { data } = await customSnippetApiService.snippets();

        expect(data).toEqual([{ type: 'plain', value: '-' }]);
    });

    it('render address used correctly', async () => {
        const { customSnippetApiService, clientMock } = createCustomSnippetApiService();

        clientMock.onPost('/_action/custom-snippet/render').reply(
            200,
            {
                rendered: 'Christa Stracke<br/> \\n \\n Philip Inlet<br/> \\n \\n \\n \\n 22005-3637 New Marilyneside<br/> \\n \\n Moldova (Republic of)<br/><br/>',
            },
        );

        const { rendered } = await customSnippetApiService.render(
            { firstName: 'Y', lastName: 'Tran' },
            [
                [
                    { value: 'address/first_name', type: 'snippet' },
                    { value: '-', type: 'plain' },
                    { value: 'address/last_name', type: 'snippet' },
                ],
            ],
        );

        expect(rendered).toBe('Christa Stracke<br/> \\n \\n Philip Inlet<br/> \\n \\n \\n \\n 22005-3637 New Marilyneside<br/> \\n \\n Moldova (Republic of)<br/><br/>');
    });
});
