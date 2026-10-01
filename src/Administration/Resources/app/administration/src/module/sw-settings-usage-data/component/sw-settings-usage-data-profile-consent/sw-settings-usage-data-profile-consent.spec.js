import { mount } from '@vue/test-utils';
import { MtSwitch } from '@shopware-ag/meteor-component-library';
import useConsentStore from 'src/core/consent/consent.store';
import SwSettingsUsageDataProfileConsent from './index';
import SwSettingsUsageDataUserDataConsentCard from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-user-data-consent-card';
import SwSettingsUsageDataConsentCheckList from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-consent-check-list';

describe('module/sw-settings-usage-data/component/sw-settings-usage-data-profile-consent', () => {
    beforeEach(() => {
        const consentStore = useConsentStore();
        consentStore.consents = {
            product_analytics: {
                status: 'unset',
            },
        };
    });

    it('shows loading indicator if consent state is not loaded', async () => {
        const consentStore = useConsentStore();
        consentStore.consents = {};

        const wrapper = await mount(SwSettingsUsageDataProfileConsent);
        const consentCard = wrapper.getComponent(SwSettingsUsageDataUserDataConsentCard);

        expect(consentCard.props('isLoading')).toBe(true);
    });

    it('shows user data consent and consent checklist', async () => {
        const wrapper = await mount(SwSettingsUsageDataProfileConsent);

        expect(wrapper.findComponent(SwSettingsUsageDataUserDataConsentCard).exists()).toBe(true);
        expect(wrapper.findComponent(SwSettingsUsageDataConsentCheckList).exists()).toBe(true);
    });

    it('accepts consent if checkbox is checked', async () => {
        const consentStore = useConsentStore();
        const acceptSpy = jest.spyOn(consentStore, 'accept');
        acceptSpy.mockImplementation(() => Promise.resolve());

        const wrapper = await mount(SwSettingsUsageDataProfileConsent);
        const checkBox = wrapper.getComponent(MtSwitch);

        await checkBox.get('input').trigger('change');

        expect(acceptSpy).toHaveBeenCalledWith('product_analytics');
    });

    it('accepts consent if checkbox is unchecked', async () => {
        const consentStore = useConsentStore();
        consentStore.consents.product_analytics.status = 'accepted';

        const revokeSpy = jest.spyOn(consentStore, 'revoke');
        revokeSpy.mockImplementation(() => Promise.resolve());

        const wrapper = await mount(SwSettingsUsageDataProfileConsent);
        const checkBox = wrapper.getComponent(MtSwitch);

        await checkBox.get('input').trigger('change');

        expect(revokeSpy).toHaveBeenCalledWith('product_analytics');
    });

    it('shows error notification if accepting consent fails', async () => {
        const consentStore = useConsentStore();
        consentStore.consents.product_analytics.status = 'accepted';

        const notificationStore = Shopware.Store.get('notification');

        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');
        const revokeSpy = jest.spyOn(consentStore, 'revoke');
        revokeSpy.mockImplementation(() => Promise.reject());

        const wrapper = await mount(SwSettingsUsageDataProfileConsent);
        const checkBox = wrapper.getComponent(MtSwitch);

        await checkBox.get('input').trigger('change');

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'sw-settings-usage-data.errors.consent-update-error',
        });
    });
});
