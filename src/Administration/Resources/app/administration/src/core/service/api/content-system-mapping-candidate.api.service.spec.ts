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

    it.each([
        [
            'missing catalogue',
            {},
        ],
        [
            'null response',
            null,
        ],
        [
            'null catalogue',
            { mappingCandidates: null },
        ],
        [
            'array catalogue',
            { mappingCandidates: [] },
        ],
        [
            'string catalogue',
            { mappingCandidates: 'invalid' },
        ],
        [
            'object instead of a root list',
            { mappingCandidates: { category: {} } },
        ],
        [
            'null instead of a root list',
            { mappingCandidates: { category: null } },
        ],
        [
            'one broken root beside a valid root',
            { mappingCandidates: { category: [], product: {} } },
        ],
    ])('rejects a %s instead of presenting an empty catalogue', async (_name, payload) => {
        const { service, clientMock } = createService();

        clientMock.onGet('/_info/content-system-mapping-candidates.json').reply(200, payload);

        await expect(service.getMappingCandidates()).rejects.toThrow('Invalid mapping candidate catalogue response.');
    });

    it.each([
        {},
        { category: [] },
    ])('accepts a valid empty catalogue %j', async (mappingCandidates) => {
        const { service, clientMock } = createService();
        clientMock.onGet('/_info/content-system-mapping-candidates.json').reply(200, { mappingCandidates });

        await expect(service.getMappingCandidates()).resolves.toEqual(mappingCandidates);
    });

    it('returns valid and malformed token spans with their revision and canonical nullability', async () => {
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
                {
                    path: 'product',
                    match: '{{map:product}}',
                    canonicalToken: null,
                    offset: 28,
                    insideMarkup: false,
                    malformed: true,
                },
            ],
        };

        clientMock
            .onPost('/_action/content-system/inline-mapping/parse', {
                text: '<p>{{  map:product.name  }} {{map:product}}</p>',
                revision: 4,
            })
            .reply(200, parsed);

        await expect(
            service.parseInlineMappingTokens('<p>{{  map:product.name  }} {{map:product}}</p>', 4),
        ).resolves.toEqual(parsed);
    });
});
