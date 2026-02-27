import { mount } from '@vue/test-utils';
import useConsentStore from 'src/core/consent/consent.store';
import { MtModal, MtModalAction, MtModalClose, MtModalRoot, MtModalTrigger } from '@shopware-ag/meteor-component-library';
import SwSettingsUsageDataConsentModalDataProvider from './index';
import SwSettingsUsageDataConsentModal from '../sw-settings-usage-data-consent-modal';

const WRONG_APP_URL_MODAL_STORAGE_KEY = 'sw-app-wrong-app-url-modal-shown';
const SHOP_ID_CHANGE_MODAL_CLASS = 'sw-app-shop-id-change-modal';

function createWrapper() {
    return mount(SwSettingsUsageDataConsentModalDataProvider, {
        global: {
            stubs: {
                Teleport: { template: '<div><slot /></div>' },
                'mt-modal': MtModal,
                'mt-modal-close': MtModalClose,
                'mt-modal-action': MtModalAction,
                'mt-modal-trigger': MtModalTrigger,
                'mt-modal-root': MtModalRoot,
            },
        },
    });
}

function getDateStringDaysAgo(daysAgo) {
    const date = new Date();
    date.setDate(date.getDate() - daysAgo);

    return date.toISOString();
}

function setConsentEligibilityContext({
    adminUserCreatedAt = getDateStringDaysAgo(20),
    firstMigrationDate = getDateStringDaysAgo(70),
    firstRunWizard = false,
    appUrlReachable = true,
    appsRequireAppUrl = false,
} = {}) {
    Shopware.Store.get('session').currentUser = {
        id: 'test-user-id',
        createdAt: adminUserCreatedAt,
    };

    Shopware.Store.get('context').app.firstRunWizard = firstRunWizard;
    Shopware.Store.get('context').app.config.settings = {
        appUrlReachable,
        appsRequireAppUrl,
        firstMigrationDate: firstMigrationDate,
    };
}

describe('/module/sw-settings-usage-data/component/sw-settings-usage-data-consent-modal-data-provider', () => {
    beforeEach(() => {
        useConsentStore().consents = {};
        localStorage.removeItem(WRONG_APP_URL_MODAL_STORAGE_KEY);
        document.body.innerHTML = '';
        setConsentEligibilityContext();
    });

    describe('consent passing', () => {
        it('doesnt show modal if consent is not loaded', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.findComponent(SwSettingsUsageDataConsentModal).exists()).toBe(false);
        });

        it('shows modal when consents are loaded', async () => {
            const consentStore = useConsentStore();
            consentStore.consents = {
                backend_data: {
                    status: 'accepted',
                },
                product_analytics: {
                    status: 'revoked',
                },
            };

            const wrapper = await createWrapper();

            expect(wrapper.findComponent(SwSettingsUsageDataConsentModal).exists()).toBe(true);
        });

        it('doesnt show modal when admin user is too new', async () => {
            const consentStore = useConsentStore();
            consentStore.consents = {
                backend_data: {
                    status: 'accepted',
                },
                product_analytics: {
                    status: 'revoked',
                },
            };
            setConsentEligibilityContext({
                adminUserCreatedAt: getDateStringDaysAgo(5),
            });

            const wrapper = await createWrapper();

            expect(wrapper.findComponent(SwSettingsUsageDataConsentModal).exists()).toBe(false);
        });

        it('doesnt show modal when shop is too new', async () => {
            const consentStore = useConsentStore();
            consentStore.consents = {
                backend_data: {
                    status: 'accepted',
                },
                product_analytics: {
                    status: 'revoked',
                },
            };
            setConsentEligibilityContext({
                firstMigrationDate: getDateStringDaysAgo(10),
            });

            const wrapper = await createWrapper();

            expect(wrapper.findComponent(SwSettingsUsageDataConsentModal).exists()).toBe(false);
        });

        it('doesnt show modal during first run wizard', async () => {
            const consentStore = useConsentStore();
            consentStore.consents = {
                backend_data: {
                    status: 'accepted',
                },
                product_analytics: {
                    status: 'revoked',
                },
            };
            setConsentEligibilityContext({
                firstRunWizard: true,
            });

            const wrapper = await createWrapper();

            expect(wrapper.findComponent(SwSettingsUsageDataConsentModal).exists()).toBe(false);
        });

        it('doesnt show modal when wrong APP_URL modal is displayed', async () => {
            const consentStore = useConsentStore();
            consentStore.consents = {
                backend_data: {
                    status: 'accepted',
                },
                product_analytics: {
                    status: 'revoked',
                },
            };
            setConsentEligibilityContext({
                appUrlReachable: false,
                appsRequireAppUrl: true,
            });

            const wrapper = await createWrapper();

            expect(wrapper.findComponent(SwSettingsUsageDataConsentModal).exists()).toBe(false);
        });

        it('doesnt show modal when shop id change modal is displayed', async () => {
            const consentStore = useConsentStore();
            consentStore.consents = {
                backend_data: {
                    status: 'accepted',
                },
                product_analytics: {
                    status: 'revoked',
                },
            };

            const shopIdChangeModal = document.createElement('div');
            shopIdChangeModal.classList.add(SHOP_ID_CHANGE_MODAL_CLASS);
            document.body.appendChild(shopIdChangeModal);

            const wrapper = await createWrapper();

            expect(wrapper.findComponent(SwSettingsUsageDataConsentModal).exists()).toBe(false);
        });

        it.each([
            [
                'unset',
                false,
                'unset',
                false,
            ],
            [
                'revoked',
                false,
                'accepted',
                true,
            ],
            [
                'accepted',
                true,
                'revoked',
                false,
            ],
        ])(
            'passes down the correct consent',
            async (initialBackenDataConsent, backendDataConsent, initialUserDataConsent, userDataConsent) => {
                const consentStore = useConsentStore();
                consentStore.consents = {
                    backend_data: {
                        status: initialBackenDataConsent,
                    },
                    product_analytics: {
                        status: initialUserDataConsent,
                    },
                };

                const wrapper = await createWrapper();
                const modal = wrapper.getComponent(SwSettingsUsageDataConsentModal);

                expect(modal.props('storedStoreDataConsent')).toBe(backendDataConsent);
                expect(modal.props('storedUserDataConsent')).toBe(userDataConsent);
            },
        );
    });
});
