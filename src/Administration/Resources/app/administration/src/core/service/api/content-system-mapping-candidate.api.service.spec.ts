/**
 * @sw-package framework
 */

import MockAdapter from 'axios-mock-adapter';
import ContentSystemMappingCandidateApiService from './content-system-mapping-candidate.api.service';
import createLoginService from '../login.service';
import createHTTPClient from '../../factory/http.factory';

function createService(): { service: ContentSystemMappingCandidateApiService; clientMock: MockAdapter } {
    const context = Shopware.Context?.api || {};
    const client = createHTTPClient(context);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);

    return {
        service: new ContentSystemMappingCandidateApiService(client, loginService),
        clientMock,
    };
}

describe('contentSystemMappingCandidateService', () => {
    it('loads the candidates grouped by root source', async () => {
        const { service, clientMock } = createService();
        const candidates = {
            category: [
                {
                    path: 'category.name',
                    source: { type: 'root', id: 'category', path: 'name' },
                    label: 'category.name',
                    description: 'Category name',
                    group: 'basic',
                    valueType: 'string',
                    compatibleTypes: ['string'],
                    inlineMappable: true,
                    canonicalToken: '{{map:category.name}}',
                    contextType: 'single',
                },
            ],
        };

        clientMock.onGet('/_info/content-system-mapping-candidates.json').reply(200, {
            mappingCandidates: candidates,
        });

        await expect(service.getMappingCandidates()).resolves.toEqual(candidates);
    });

    it('returns an empty catalogue when the response shape is invalid', async () => {
        const { service, clientMock } = createService();

        clientMock.onGet('/_info/content-system-mapping-candidates.json').reply(200, {
            mappingCandidates: [],
        });

        await expect(service.getMappingCandidates()).resolves.toEqual({});
    });

    it('returns server-parsed inline mapping token spans with their revision', async () => {
        const { service, clientMock } = createService();
        const parsed = {
            revision: 4,
            tokens: [
                {
                    path: 'product.name',
                    match: '{{  map:product.name  }}',
                    canonicalToken: '{{map:product.name}}',
                    offset: 3,
                    insideMarkup: false,
                    malformed: false,
                },
            ],
        };

        clientMock.onPost('/_action/content-system/inline-mapping/parse').reply(200, parsed);

        await expect(service.parseInlineMappingTokens('<p>{{map:product.name}}</p>', 4)).resolves.toEqual(parsed);
    });
});
