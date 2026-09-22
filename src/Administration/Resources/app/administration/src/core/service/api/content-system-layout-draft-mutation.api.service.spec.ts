/**
 * @sw-package framework
 */

import MockAdapter from 'axios-mock-adapter';
import ContentSystemLayoutDraftMutationApiService from './content-system-layout-draft-mutation.api.service';
import createLoginService from '../login.service';
import createHTTPClient from '../../factory/http.factory';

function createService(): { service: ContentSystemLayoutDraftMutationApiService; clientMock: MockAdapter } {
    const context = Shopware.Context?.api || {};
    const client = createHTTPClient(context);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);

    return {
        service: new ContentSystemLayoutDraftMutationApiService(client, loginService),
        clientMock,
    };
}

describe('contentSystemLayoutDraftMutationService', () => {
    it('diagnoses a draft layout with its root source', async () => {
        const { service, clientMock } = createService();
        const payload = {
            layout: [{ id: 'text', component: 'Sw:Content:Text' }],
            rootSource: 'category',
        };
        const response = {
            resolutions: {},
            diagnostics: {
                wellFormed: true,
                resolvable: false,
                violations: [
                    {
                        code: 'invalid_mapping',
                        scope: 'binding',
                        severity: 'error',
                        elementId: 'text',
                        key: 'text',
                        message: 'Invalid mapping',
                        candidates: [],
                    },
                ],
            },
        };

        clientMock.onPost('/_action/content-system/layout/diagnose', payload).reply(200, response);

        await expect(service.diagnose(payload)).resolves.toEqual(response);
    });
});
