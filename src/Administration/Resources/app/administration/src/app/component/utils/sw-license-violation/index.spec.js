/**
 * @sw-package framework
 */

import LicenseViolationComponent from './index';
import 'src/app/store/license-violation.store';
import { mount } from '@vue/test-utils';

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

    it.each([
        [true, true],
        [false, false],
    ])('shows the remove action only when allowUpdate is %s', async (allowUpdate, canRemove) => {
        const wrapper = mount(await wrapTestComponent('sw-license-violation', { sync: true }), {
            global: {
                provide: {
                    cacheApiService: {},
                    extensionStoreActionService: { getMyExtensions: jest.fn(() => Promise.resolve([])) },
                    licenseViolationService: {},
                    loginService: { isLoggedIn: () => false },
                },
                mocks: { $route: {} },
                stubs: {
                    'sw-modal': { template: '<div><slot /></div>' },
                    'mt-button': { template: '<button><slot /></button>' },
                    'mt-checkbox': true,
                },
            },
        });

        Shopware.Store.get('licenseViolation').violations = [
            {
                ...violation,
                extensions: { licenseViolation: { text: 'Violation', actions: [{ externalLink: '#', label: 'Buy' }] } },
            },
        ];
        await wrapper.setData({
            showViolation: true,
            plugins: [{ name: violation.name, allowUpdate }],
        });

        expect(wrapper.find('.sw-license-violation__table-action-remove').exists()).toBe(canRemove);
        expect(wrapper.text().includes('sw-license-violation.removeExternally')).toBe(!canRemove);
        wrapper.unmount();
    });
});
