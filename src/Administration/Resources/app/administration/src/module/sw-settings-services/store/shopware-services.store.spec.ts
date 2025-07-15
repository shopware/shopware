import { createPinia, setActivePinia } from 'pinia';
import { useShopwareServicesStore } from './shopware-services.store';

describe('src/module/sw-settings-services/store/shopware-services.store.ts', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('initializes the store with initial state', () => {
        const shopwareServicesStore = useShopwareServicesStore();

        expect(shopwareServicesStore.config).toBeNull();
        expect(shopwareServicesStore.revisions).toBeNull();
        expect(shopwareServicesStore.showGrantPermissionsModal).toBe(false);
    });

    it.each([
        [
            undefined,
            null,
            false,
        ],
        [
            {
                identifier: 'id',
                revision: '2025-07-08',
                consentingUserId: 'user-id',
                grantedAt: '2025-07-08T00:00:00Z',
            },
            null,
            false,
        ],
        [
            {
                identifier: 'id',
                revision: '2025-07-08',
                consentingUserId: 'user-id',
                grantedAt: '2025-07-08T00:00:00Z',
            },
            {
                'latest-revision': '2025-08-08',
                'available-revisions': [],
            },
            false,
        ],
        [
            {
                identifier: 'id',
                revision: '2025-07-08',
                consentingUserId: 'user-id',
                grantedAt: '2025-07-08T00:00:00Z',
            },
            {
                'latest-revision': '2025-07-08',
                'available-revisions': [],
            },
            true,
        ],
    ])('determines the consent given state', (permissionsConsent, revisions, isConsentGiven) => {
        const shopwareServicesStore = useShopwareServicesStore();

        shopwareServicesStore.config = { permissionsConsent };
        shopwareServicesStore.revisions = revisions;

        expect(shopwareServicesStore.consentGiven).toBe(isConsentGiven);
    });

    it.each([
        [
            null,
            null,
        ],
        [
            {
                'latest-revision': '2025-07-08',
                'available-revisions': [],
            },
            null,
        ],
        [
            {
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
            {
                revision: '2025-07-08',
                links: {
                    'feedback-url': 'https://example.com/feedback',
                    'docs-url': 'https://example.com/docs',
                    'tos-url': 'https://example.com/tos',
                },
            },
        ],
    ])('determines the current permissions revision', (revisions, currentRevision) => {
        const shopwareServicesStore = useShopwareServicesStore();

        shopwareServicesStore.revisions = revisions;

        expect(shopwareServicesStore.currentRevision).toEqual(currentRevision);
    });
});
