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
            .onPost('/_action/content-system/inline-mapping/parse', {
                text: '<p>{{  map:product.name  }} {{map:product}}</p>',
                revision: 4,
            })
            .reply(200, parsed);

        await expect(
            service.parseInlineMappingTokens('<p>{{  map:product.name  }} {{map:product}}</p>', 4),
        ).resolves.toEqual(parsed);
    });

    it('processes twenty queued fields in FIFO order with at most three requests in flight', async () => {
        const { service, clientMock } = createService();
        const finishRequests: Array<() => void> = [];
        let activeRequests = 0;
        let maximumActiveRequests = 0;
        clientMock.onPost('/_action/content-system/inline-mapping/parse').reply(
            (config) =>
                new Promise<[number, ContentSystemInlineMappingParseResponse]>((resolve) => {
                    const { revision } = JSON.parse(config.data as string) as { revision: number };
                    activeRequests += 1;
                    maximumActiveRequests = Math.max(maximumActiveRequests, activeRequests);
                    finishRequests.push(() => {
                        activeRequests -= 1;
                        resolve([200, { revision, tokens: [] }]);
                    });
                }),
        );
        const requests = Array.from({ length: 20 }, (_, revision) => ({ text: `Field ${revision}`, revision }));
        const results = Promise.all(requests.map(({ text, revision }) => service.parseInlineMappingTokens(text, revision)));
        await flushPromises();

        expect(clientMock.history.post).toHaveLength(3);
        for (let index = 0; index < requests.length; index += 1) {
            finishRequests[index]();
            await flushPromises();
            expect(activeRequests).toBe(Math.min(3, requests.length - index - 1));
        }

        await expect(results).resolves.toEqual(requests.map(({ revision }) => ({ revision, tokens: [] })));
        expect(maximumActiveRequests).toBe(3);
        expect(clientMock.history.post.map(({ data }) => JSON.parse(data as string) as unknown)).toEqual(requests);
    });

    it('preserves duplicate requests and parses the same text again later', async () => {
        const { service, clientMock } = createService();
        const request = { text: '<p>Same text</p>', revision: 1 };
        const parsed = { revision: 1, tokens: [] };
        clientMock.onPost('/_action/content-system/inline-mapping/parse', request).reply(200, parsed);

        await expect(
            Promise.all([
                service.parseInlineMappingTokens(request.text, request.revision),
                service.parseInlineMappingTokens(request.text, request.revision),
            ]),
        ).resolves.toEqual([parsed, parsed]);
        await expect(service.parseInlineMappingTokens(request.text, request.revision)).resolves.toEqual(parsed);
        expect(clientMock.history.post).toHaveLength(3);
    });

    it('rejects only the field whose parse request fails', async () => {
        const { service, clientMock } = createService();
        const parsed = { revision: 2, tokens: [] };
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse', { text: 'Oversized text', revision: 1 })
            .reply(400, { errors: [{ code: 'CONTENT_SYSTEM__INLINE_MAPPING_INPUT_TOO_LARGE' }] });
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse', { text: 'Valid text', revision: 2 })
            .reply(200, parsed);

        const oversized = service.parseInlineMappingTokens('Oversized text', 1);
        const valid = service.parseInlineMappingTokens('Valid text', 2);

        await expect(oversized).rejects.toThrow();
        await expect(valid).resolves.toEqual(parsed);
    });

    it('releases failed request slots so queued fields and later retries can complete', async () => {
        const { service, clientMock } = createService();
        clientMock.onPost('/_action/content-system/inline-mapping/parse').reply((config) => {
            const { revision } = JSON.parse(config.data as string) as { revision: number };
            return revision < 3 ? [503, {}] : [200, { revision, tokens: [] }];
        });

        const results = await Promise.allSettled(
            Array.from({ length: 4 }, (_, revision) => service.parseInlineMappingTokens(`Field ${revision}`, revision)),
        );

        expect(results.map(({ status }) => status)).toEqual([
            'rejected',
            'rejected',
            'rejected',
            'fulfilled',
        ]);
        expect(results[3]).toEqual({ status: 'fulfilled', value: { revision: 3, tokens: [] } });
        await expect(service.parseInlineMappingTokens('Retry', 4)).resolves.toEqual({ revision: 4, tokens: [] });
        expect(clientMock.history.post).toHaveLength(5);
    });

    it('keeps a new revision independent of an older request that is still in flight', async () => {
        const { service, clientMock } = createService();
        const firstParsed = { revision: 1, tokens: [] };
        const secondParsed = { revision: 2, tokens: [] };
        let finishFirstRequest!: () => void;
        const firstReply = new Promise<[number, ContentSystemInlineMappingParseResponse]>((resolve) => {
            finishFirstRequest = () => resolve([200, firstParsed]);
        });
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse', { text: 'First value', revision: 1 })
            .reply(() => firstReply);
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse', { text: 'New value', revision: 2 })
            .reply(200, secondParsed);

        const first = service.parseInlineMappingTokens('First value', 1);
        const onFirstResult = jest.fn();
        void first.then(onFirstResult);
        await flushPromises();

        await expect(service.parseInlineMappingTokens('New value', 2)).resolves.toEqual(secondParsed);
        expect(onFirstResult).not.toHaveBeenCalled();

        finishFirstRequest();
        await expect(first).resolves.toEqual(firstParsed);
        expect(clientMock.history.post).toHaveLength(2);
    });

    it.each([
        null,
        {},
        { tokens: [] },
        { revision: 1, tokens: null },
        { revision: '1', tokens: [] },
    ])('rejects an invalid response %j without affecting another field', async (payload) => {
        const { service, clientMock } = createService();
        const parsed = { revision: 2, tokens: [] };
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse', { text: 'First', revision: 1 })
            .reply(200, payload);
        clientMock
            .onPost('/_action/content-system/inline-mapping/parse', { text: 'Second', revision: 2 })
            .reply(200, parsed);

        const invalid = service.parseInlineMappingTokens('First', 1);
        const valid = service.parseInlineMappingTokens('Second', 2);

        await expect(invalid).rejects.toThrow('Invalid inline mapping parse response.');
        await expect(valid).resolves.toEqual(parsed);
    });
});
