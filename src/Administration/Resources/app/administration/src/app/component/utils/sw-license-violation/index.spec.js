/**
 * @sw-package framework
 */

import LicenseViolationComponent from './index';
import 'src/app/store/license-violation.store';

describe('src/app/component/utils/sw-license-violation', () => {
    const violation = {
        name: 'RemovedExtension',
        extensions: {
            licenseViolation: {
                type: { level: 'violation' },
            },
        },
    };

    const createContext = ({ showViolation, extensions = [] }) => {
        const licenseViolationService = {
            checkForLicenseViolations: jest.fn(() =>
                Promise.resolve({
                    violations: [violation],
                    warnings: [],
                    other: [],
                }),
            ),
            getViolationsFromCache: jest.fn(() => [violation]),
            isTimeExpired: jest.fn(() => showViolation),
            key: {
                showViolationsKey: 'licenseViolationShowViolations',
            },
            saveViolationsToCache: jest.fn(),
        };

        return {
            addLoading: jest.fn(),
            extensionStoreActionService: {
                getMyExtensions: jest.fn(() => Promise.resolve(extensions)),
            },
            finishLoading: jest.fn(),
            licenseViolationService,
            loginService: {
                isLoggedIn: jest.fn(() => true),
            },
            showViolation: false,
        };
    };

    beforeEach(() => {
        const licenseViolationStore = Shopware.Store.get('licenseViolation');
        licenseViolationStore.violations = [];
        licenseViolationStore.warnings = [];
        licenseViolationStore.other = [];
    });

    it('does not load extensions when the violation modal is not visible', async () => {
        const context = createContext({ showViolation: false });

        await LicenseViolationComponent.methods.getPluginViolation.call(context);

        expect(context.extensionStoreActionService.getMyExtensions).not.toHaveBeenCalled();
        expect(Shopware.Store.get('licenseViolation').violations).toEqual([violation]);
    });

    it('removes cached violations for extensions that are no longer installed before opening the modal', async () => {
        const context = createContext({ showViolation: true });

        await LicenseViolationComponent.methods.getPluginViolation.call(context);

        expect(context.extensionStoreActionService.getMyExtensions).toHaveBeenCalled();
        expect(context.licenseViolationService.saveViolationsToCache).toHaveBeenCalledWith([]);
        expect(Shopware.Store.get('licenseViolation').violations).toEqual([]);
    });
});
