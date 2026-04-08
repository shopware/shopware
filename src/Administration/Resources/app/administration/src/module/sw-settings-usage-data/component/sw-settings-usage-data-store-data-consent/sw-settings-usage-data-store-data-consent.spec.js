import { mount } from '@vue/test-utils';
import { MtSwitch } from '@shopware-ag/meteor-component-library';
import useConsentStore from 'src/core/consent/consent.store';
import SwSettingsUsageDataStoreDataConsent from './index';

import SwSettingsUsageDataStoreDataConsentCard from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-store-data-consent-card';
import SwSettingsUsageDataConsentCheckList from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-consent-check-list';

describe('module/sw-settings-usage-data/component/sw-settings-usage-data-store-data-consent', () => {
    beforeEach(() => {
        const consentStore = useConsentStore();
        consentStore.consents = {
            backend_data: {
                status: 'unset',
            },
        };
    });

    it('shows loading indicator if consent state is not loaded', async () => {
        const consentStore = useConsentStore();
        consentStore.consents = {};

        const wrapper = await mount(SwSettingsUsageDataStoreDataConsent);
        const consentCard = wrapper.getComponent(SwSettingsUsageDataStoreDataConsentCard);

        expect(consentCard.props('isLoading')).toBe(true);
    });

    it('shows user data consent and consent checklist', async () => {
        const wrapper = await mount(SwSettingsUsageDataStoreDataConsent);

        expect(wrapper.findComponent(SwSettingsUsageDataStoreDataConsentCard).exists()).toBe(true);
        expect(wrapper.findComponent(SwSettingsUsageDataConsentCheckList).exists()).toBe(true);
    });

    it('accepts consent if checkbox is checked', async () => {
        const consentStore = useConsentStore();
        const acceptSpy = jest.spyOn(consentStore, 'accept');
        acceptSpy.mockImplementation(() => Promise.resolve());

        const wrapper = await mount(SwSettingsUsageDataStoreDataConsent);
        const checkBox = wrapper.getComponent(MtSwitch);

        await checkBox.get('input').trigger('change');

        expect(acceptSpy).toHaveBeenCalledWith('backend_data');
    });

    it('accepts consent if checkbox is unchecked', async () => {
        const consentStore = useConsentStore();
        consentStore.consents.backend_data.status = 'accepted';

        const revokeSpy = jest.spyOn(consentStore, 'revoke');
        revokeSpy.mockImplementation(() => Promise.resolve());

        const wrapper = await mount(SwSettingsUsageDataStoreDataConsent);
        const checkBox = wrapper.getComponent(MtSwitch);

        await checkBox.get('input').trigger('change');

        expect(revokeSpy).toHaveBeenCalledWith('backend_data');
    });

    it('shows error notification if accepting consent fails', async () => {
        const consentStore = useConsentStore();
        consentStore.consents.backend_data.status = 'accepted';

        const notificationStore = Shopware.Store.get('notification');

        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');
        const revokeSpy = jest.spyOn(consentStore, 'revoke');
        revokeSpy.mockImplementation(() => Promise.reject());

        const wrapper = await mount(SwSettingsUsageDataStoreDataConsent);
        const checkBox = wrapper.getComponent(MtSwitch);

        await checkBox.get('input').trigger('change');

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'sw-settings-usage-data.errors.consent-update-error',
        });
    });
});
