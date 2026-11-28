import { createPinia, setActivePinia } from 'pinia';
import * as permissions from './permissions';
import { useShopwareServicesStore, SERVICE_CONSENT_NAME } from '../store/shopware-services.store';
import useConsentStore from 'src/core/consent/consent.store';

describe('src/module/sw-settings-services/composables/permissions', () => {
    let reloadMock;
    const serviceOrigin = 'https://copilot.staging-apps.shopware.io';

    const serviceRequest = () => ({
        _event_: new MessageEvent('message', { origin: serviceOrigin }),
    });

    const serviceConsent = (status, acceptedRevision) => ({
        name: SERVICE_CONSENT_NAME,
        identifier: 'system',
        scopeName: 'system',
        actor: null,
        status,
        updatedAt: null,
        acceptedRevision,
        latestRevision: '2025-06-25',
    });

    beforeAll(() => {
        Shopware.Service().register('consentApiService', () => ({
            accept: jest.fn(async (consent, revision) => ({
                data: {
                    ...serviceConsent('accepted', revision),
                    name: consent,
                },
            })),
            revoke: jest.fn(async (consent) => ({
                data: {
                    ...serviceConsent('revoked', null),
                    name: consent,
                },
            })),
        }));
        Shopware.Service().register('shopwareServicesService', () => ({
            getServicesContext: jest.fn(async () => ({ disabled: false })),
        }));
        reloadMock = jest.fn();
        permissions.__setReloadFn(reloadMock);
    });

    beforeEach(() => {
        reloadMock.mockClear();
        setActivePinia(createPinia());
        useShopwareServicesStore();
        useConsentStore().consents = {
            [SERVICE_CONSENT_NAME]: serviceConsent('unset', null),
        };
        Shopware.Store.get('extensions').extensionsState = {
            SwagCopilot: {
                name: 'SwagCopilot',
                baseUrl: serviceOrigin,
                permissions: {},
                type: 'app',
                sourceType: 'service',
            },
        };
    });

    it('accepts the latest revision of the service consent and reloads', async () => {
        await permissions.grantPermissions();

        expect(Shopware.Service('consentApiService').accept).toHaveBeenCalledWith(SERVICE_CONSENT_NAME, '2025-06-25');
        expect(reloadMock).toHaveBeenCalled();
    });

    it('throws if the service consent is not loaded', async () => {
        useConsentStore().consents = {};

        await expect(() => permissions.grantPermissions()).rejects.toThrow(/not found in store/);
        expect(reloadMock).not.toHaveBeenCalled();
    });

    it('revokes the service consent and reloads', async () => {
        await permissions.revokePermissions();

        expect(Shopware.Service('consentApiService').revoke).toHaveBeenCalledWith(SERVICE_CONSENT_NAME);
        expect(reloadMock).toHaveBeenCalled();
    });

    it('grants permissions and reloads the Administration for a Service SDK request', async () => {
        await permissions.grantPermissionsFromSdk({}, serviceRequest());

        expect(Shopware.Service('consentApiService').accept).toHaveBeenCalledWith(SERVICE_CONSENT_NAME, '2025-06-25');
        expect(reloadMock).toHaveBeenCalled();
    });

    it('rejects SDK permission grants from non-Service origins', () => {
        Shopware.Store.get('extensions').extensionsState = {
            SomeService: {
                name: 'SomeService',
                baseUrl: 'https://api.example.company',
                permissions: {},
                type: 'app',
                sourceType: 'service',
            },
        };

        expect(() =>
            permissions.grantPermissionsFromSdk(
                {},
                { _event_: new MessageEvent('message', { origin: 'https://api.example.com' }) },
            ),
        ).toThrow('Only Shopware Services can access this handler.');
    });

    it('rejects SDK permission grants when a local extension shares the Service origin', () => {
        Shopware.Store.get('extensions').extensionsState = {
            SomeService: {
                name: 'SomeService',
                baseUrl: serviceOrigin,
                permissions: {},
                type: 'app',
                sourceType: 'service',
            },
            SomeLocalExtension: {
                name: 'SomeLocalExtension',
                baseUrl: serviceOrigin,
                permissions: {},
                type: 'app',
                sourceType: 'local',
            },
        };

        expect(() => permissions.grantPermissionsFromSdk({}, serviceRequest())).toThrow(
            'Only Shopware Services can access this handler.',
        );
    });

    it('rejects SDK permission status requests from non-Service origins', async () => {
        Shopware.Store.get('extensions').extensionsState = {
            SomeApp: {
                name: 'SomeApp',
                baseUrl: 'https://app.example.com',
                permissions: {},
                type: 'app',
                sourceType: null,
            },
        };

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).rejects.toThrow(
            'Only Shopware Services can access this handler.',
        );
    });

    it('reports permission as granted when Shopware Services are disabled', async () => {
        useShopwareServicesStore().config = { disabled: true };

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).resolves.toBe(true);
    });

    it('reports permission as granted when the latest revision has been consented to', async () => {
        useShopwareServicesStore().config = { disabled: false };
        useConsentStore().consents = {
            [SERVICE_CONSENT_NAME]: serviceConsent('accepted', '2025-06-25'),
        };

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).resolves.toBe(true);
    });

    it('reports permission as not granted when no consent exists for the latest revision', async () => {
        useShopwareServicesStore().config = { disabled: false };
        useConsentStore().consents = {
            [SERVICE_CONSENT_NAME]: serviceConsent('accepted', '2024-01-01'),
        };

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).resolves.toBe(false);
    });

    it('loads the services context on demand when it is not present yet', async () => {
        Shopware.Service('shopwareServicesService').getServicesContext.mockResolvedValueOnce({ disabled: true });

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).resolves.toBe(true);
        expect(Shopware.Service('shopwareServicesService').getServicesContext).toHaveBeenCalled();
    });
});
