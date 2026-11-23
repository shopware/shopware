/**
 * @sw-package framework
 */

import MockAdapter from 'axios-mock-adapter';
import ContentSystemMappingCandidateApiService from './content-system-mapping-candidate.api.service';
import type { ContentSystemInlineMappingParseResponse } from './content-system-mapping-candidate.api.service';
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
            .onPost('/_action/content-system/inline-mapping/parse-batch', {
                requests: [{ text: '<p>{{  map:product.name  }} {{map:product}}</p>', revision: 4 }],
            })
            .reply(200, { results: [parsed] });

        await expect(
            service.parseInlineMappingTokens('<p>{{  map:product.name  }} {{map:product}}</p>', 4),
        ).resolves.toEqual(parsed);
    });

    it('sends twenty simultaneous field parses in one request and returns each field its own result', async () => {
        const { service, clientMock } = createService();
        const requests = Array.from({ length: 20 }, (_, revision) => ({ text: `Field ${revision}`, revision }));
        const results = requests.map(({ revision }) => ({ revision, tokens: [] }));
        clientMock.onPost('/_action/content-system/inline-mapping/parse-batch', { requests }).reply(200, { results });

        await expect(
            Promise.all(requests.map(({ text, revision }) => service.parseInlineMappingTokens(text, revision))),
        ).resolves.toEqual(results);
        expect(clientMock.history.post).toHaveLength(1);
    });

    it('preserves duplicate requests and parses the same text again in a later batch', async () => {
        const { service, clientMock } = createService();
        const request = { text: '<p>Same text</p>', revision: 1 };
        const parsed = { revision: 1, tokens: [] };
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse-batch', { requests: [request, request] })
            .reply(200, { results: [parsed, parsed] });
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse-batch', { requests: [request] })
            .reply(200, { results: [parsed] });

        await expect(
            Promise.all([
                service.parseInlineMappingTokens(request.text, request.revision),
                service.parseInlineMappingTokens(request.text, request.revision),
            ]),
        ).resolves.toEqual([parsed, parsed]);
        await expect(service.parseInlineMappingTokens(request.text, request.revision)).resolves.toEqual(parsed);
        expect(clientMock.history.post).toHaveLength(2);
    });

    it('rejects only the field with a parser input error', async () => {
        const { service, clientMock } = createService();
        const parsed = { revision: 2, tokens: [] };
        clientMock.onPost('/_action/content-system/inline-mapping/parse-batch').reply(200, {
            results: [
                {
                    revision: 1,
                    error: { code: 'CONTENT_SYSTEM__INLINE_MAPPING_INPUT_TOO_LARGE', message: 'Text is too large.' },
                },
                parsed,
            ],
        });

        const oversized = service.parseInlineMappingTokens('Oversized text', 1);
        const valid = service.parseInlineMappingTokens('Valid text', 2);

        await expect(oversized).rejects.toThrow('Text is too large.');
        await expect(valid).resolves.toEqual(parsed);
    });

    it('rejects every field on a transport failure and allows a later request to succeed', async () => {
        const { service, clientMock } = createService();
        clientMock.onPost('/_action/content-system/inline-mapping/parse-batch').replyOnce(503);
        clientMock.onPost('/_action/content-system/inline-mapping/parse-batch').reply(200, {
            results: [{ revision: 3, tokens: [] }],
        });

        const results = await Promise.allSettled([
            service.parseInlineMappingTokens('First', 1),
            service.parseInlineMappingTokens('Second', 2),
        ]);

        expect(results.map(({ status }) => status)).toEqual(['rejected', 'rejected']);
        await expect(service.parseInlineMappingTokens('Retry', 3)).resolves.toEqual({ revision: 3, tokens: [] });
    });

    it('keeps a new revision independent of a batch that is still in flight', async () => {
        const { service, clientMock } = createService();
        const firstParsed = { revision: 1, tokens: [] };
        const secondParsed = { revision: 2, tokens: [] };
        let finishFirstRequest = () => {};
        const firstReply = new Promise<[number, { results: ContentSystemInlineMappingParseResponse[] }]>((resolve) => {
            finishFirstRequest = () => resolve([200, { results: [firstParsed] }]);
        });
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse-batch', {
                requests: [{ text: 'First value', revision: 1 }],
            })
            .reply(() => firstReply);
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse-batch', {
                requests: [{ text: 'New value', revision: 2 }],
            })
            .reply(200, { results: [secondParsed] });

        const first = service.parseInlineMappingTokens('First value', 1);
        const onFirstResult = jest.fn();
        void first.then(onFirstResult);
        await Promise.resolve();

        await expect(service.parseInlineMappingTokens('New value', 2)).resolves.toEqual(secondParsed);
        expect(onFirstResult).not.toHaveBeenCalled();

        finishFirstRequest();
        await expect(first).resolves.toEqual(firstParsed);
        expect(clientMock.history.post).toHaveLength(2);
    });

    it.each([null, {}, { results: [] }])('rejects every pending field for an incomplete response %j', async (payload) => {
        const { service, clientMock } = createService();
        clientMock.onPost('/_action/content-system/inline-mapping/parse-batch').reply(200, payload);

        const results = await Promise.allSettled([
            service.parseInlineMappingTokens('First', 1),
            service.parseInlineMappingTokens('Second', 2),
        ]);

        expect(results).toEqual([
            { status: 'rejected', reason: new Error('Invalid inline mapping parse batch response.') },
            { status: 'rejected', reason: new Error('Invalid inline mapping parse batch response.') },
        ]);
    });

    it('splits requests at the server batch limit while preserving their order', async () => {
        const { service, clientMock } = createService();
        const requests = Array.from({ length: 51 }, (_, revision) => ({ text: `Field ${revision}`, revision }));
        const results = requests.map(({ revision }) => ({ revision, tokens: [] }));
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse-batch', { requests: requests.slice(0, 50) })
            .reply(200, { results: results.slice(0, 50) });
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse-batch', { requests: requests.slice(50) })
            .reply(200, { results: results.slice(50) });

        await expect(
            Promise.all(requests.map(({ text, revision }) => service.parseInlineMappingTokens(text, revision))),
        ).resolves.toEqual(results);
        expect(clientMock.history.post).toHaveLength(2);
    });
});
