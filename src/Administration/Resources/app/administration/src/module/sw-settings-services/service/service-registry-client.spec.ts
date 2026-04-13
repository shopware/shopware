import ServiceRegistryClient from './service-registry-client';

const registryURL = 'https://registry.services.shopware.io';
const registryClient = new ServiceRegistryClient(registryURL);
const revisionsResponse = {
    revisions: {
        'latest-revision': '2025-07-08',
        'available-revisions': [
            {
                revision: '2025-07-08',
                links: {
                    'feedback-url': 'https://example.com/feedback',
                    'docs-url': 'https://example.com/docs',
                    'tos-url': 'https://example.com/tos',
                },
            },
            {
                revision: '2025-01-01',
                links: {
                    'feedback-url': 'https://example.com/feedback',
                    'docs-url': 'https://example.com/docs',
                    'tos-url': 'https://example.com/tos',
                },
            },
        ],
    },
};

describe('src/module/sw-settings-services/service/service-registry-client.ts', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('fetches the current permissions revision', async () => {
        // @ts-expect-error
        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve(revisionsResponse),
            }),
        );

        const currentRevision = await registryClient.getCurrentRevision('en-US');

        expect(currentRevision).toBe(revisionsResponse.revisions);
    });

    it('throws an error if the response does not contain permissions revisions', async () => {
        // @ts-expect-error
        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({}),
            }),
        );

        await expect(async () => {
            await registryClient.getCurrentRevision('en-US');
        }).rejects.toThrow('Could not fetch Revision data from Service Registry');
    });
});
