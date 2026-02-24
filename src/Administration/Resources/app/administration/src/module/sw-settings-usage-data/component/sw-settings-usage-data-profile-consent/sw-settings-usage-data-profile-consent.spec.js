import { mount } from '@vue/test-utils';
import SwSettingsUsageDataProfileConsent from './index';
import SwSettingsUsageDataUserDataConsentCard from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-user-data-consent-card';
import SwSettingsUsageDataConsentCheckList from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-consent-check-list';

describe('module/sw-settings-usage-data/component/sw-settings-usage-data-profile-consent', () => {
    it('shows user data consent and consent checklist', async () => {
        const wrapper = await mount(SwSettingsUsageDataProfileConsent);

        expect(wrapper.findComponent(SwSettingsUsageDataUserDataConsentCard).exists()).toBe(true);
        expect(wrapper.findComponent(SwSettingsUsageDataConsentCheckList).exists()).toBe(true);
    });
});
