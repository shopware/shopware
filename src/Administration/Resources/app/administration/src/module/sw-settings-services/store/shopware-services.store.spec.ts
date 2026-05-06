import { createPinia, setActivePinia } from 'pinia';
import { useShopwareServicesStore } from './shopware-services.store';
import useConsentStore from 'src/core/consent/consent.store';
import { SERVICE_CONSENT_NAME } from './shopware-services.store';

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
            null,
            null,
            false,
        ],
        [
            {
                name: SERVICE_CONSENT_NAME,
                identifier: 'system',
                scopeName: 'system',
                actor: 'user-id',
                status: 'accepted',
                updatedAt: '2025-07-08T00:00:00Z',
                acceptedRevision: '2025-07-08',
                latestRevision: '2025-07-08',
            },
            null,
            false,
        ],
        [
            {
                name: SERVICE_CONSENT_NAME,
                identifier: 'system',
                scopeName: 'system',
                actor: 'user-id',
                status: 'accepted',
                updatedAt: '2025-07-08T00:00:00Z',
                acceptedRevision: '2025-07-08',
                latestRevision: '2025-07-08',
            },
            {
                'latest-revision': '2025-08-08',
                'available-revisions': [],
            },
            false,
        ],
        [
            {
                name: SERVICE_CONSENT_NAME,
                identifier: 'system',
                scopeName: 'system',
                actor: 'user-id',
                status: 'accepted',
                updatedAt: '2025-07-08T00:00:00Z',
                acceptedRevision: '2025-07-08',
                latestRevision: '2025-07-08',
            },
            {
                'latest-revision': '2025-07-08',
                'available-revisions': [],
            },
            true,
        ],
    ])('determines the consent given state', (serviceConsent, revisions, isConsentGiven) => {
        const shopwareServicesStore = useShopwareServicesStore();
        const consentStore = useConsentStore();

        consentStore.consents = serviceConsent ? { [SERVICE_CONSENT_NAME]: serviceConsent } : {};
        shopwareServicesStore.config = {};
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
