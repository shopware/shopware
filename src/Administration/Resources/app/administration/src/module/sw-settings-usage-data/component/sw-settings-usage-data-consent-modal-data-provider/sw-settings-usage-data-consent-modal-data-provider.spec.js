import { mount } from '@vue/test-utils';
import useConsentStore from 'src/core/consent/consent.store';
import { MtModal, MtModalAction, MtModalClose, MtModalRoot, MtModalTrigger } from '@shopware-ag/meteor-component-library';
import SwSettingsUsageDataConsentModalDataProvider from './index';
import SwSettingsUsageDataConsentModal from '../sw-settings-usage-data-consent-modal';

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

describe('/module/sw-settings-usage-data/component/sw-settings-usage-data-consent-modal-data-provider', () => {
    beforeEach(() => {
        useConsentStore().consents = {};
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
