import { createPinia, setActivePinia } from 'pinia';
import * as permissions from './permissions';
import { SERVICE_CONSENT_NAME } from '../store/shopware-services.store';
import useConsentStore from 'src/core/consent/consent.store';

describe('src/module/sw-settings-services/composables/permissions', () => {
    let reloadMock;

    beforeAll(() => {
        Shopware.Service().register('consentApiService', () => ({
            accept: jest.fn(async (consent, revision) => ({
                data: {
                    name: consent,
                    identifier: 'system',
                    scopeName: 'system',
                    actor: 'user-id',
                    status: 'accepted',
                    updatedAt: '2026-05-05T12:00:00.000Z',
                    acceptedRevision: revision,
                    latestRevision: revision,
                },
            })),
            revoke: jest.fn(async (consent) => ({
                data: {
                    name: consent,
                    identifier: 'system',
                    scopeName: 'system',
                    actor: 'user-id',
                    status: 'revoked',
                    updatedAt: '2026-05-05T12:00:00.000Z',
                    acceptedRevision: null,
                    latestRevision: '2025-06-25',
                },
            })),
        }));
        reloadMock = jest.fn();
        permissions.__setReloadFn(reloadMock);
    });

    beforeEach(() => {
        reloadMock.mockClear();
        setActivePinia(createPinia());
        const consentStore = useConsentStore();
        consentStore.consents = {
            [SERVICE_CONSENT_NAME]: {
                name: SERVICE_CONSENT_NAME,
                identifier: 'system',
                scopeName: 'system',
                actor: null,
                status: 'unset',
                updatedAt: null,
                acceptedRevision: null,
                latestRevision: '2025-06-25',
            },
        };
    });

    it('calls shopware service and reloads', async () => {
        await permissions.grantPermissions();

        // latest revision is taken from the consent itself (set in beforeEach), not a separate fetch
        expect(Shopware.Service('consentApiService').accept).toHaveBeenCalledWith(SERVICE_CONSENT_NAME, '2025-06-25');
        expect(reloadMock).toHaveBeenCalled();
    });

    it('throws if the service consent is not loaded', async () => {
        useConsentStore().consents = {};

        await expect(() => permissions.grantPermissions()).rejects.toThrow(/not found in store/);
        expect(reloadMock).not.toHaveBeenCalled();
    });

    it('calls shopware service to revoke permissions and reloads', async () => {
        await permissions.revokePermissions();

        expect(Shopware.Service('consentApiService').revoke).toHaveBeenCalledWith(SERVICE_CONSENT_NAME);
        expect(reloadMock).toHaveBeenCalled();
    });
});
