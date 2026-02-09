import { mount } from '@vue/test-utils';
import SwSettingsUsageDataStoreDataConsent from './index';

/* eslint-disable max-len */
import SwSettingsUsageDataStoreDataConsentCard from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-store-data-consent-card';
import SwSettingsUsageDataConsentCheckList from '../sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-consent-check-list';
/* eslint-enable max-len */

describe('module/sw-settings-usage-data/component/sw-settings-usage-data-store-data-consent', () => {
    it('shows user data consent and consent checklist', async () => {
        const wrapper = await mount(SwSettingsUsageDataStoreDataConsent);

        expect(wrapper.findComponent(SwSettingsUsageDataStoreDataConsentCard).exists()).toBe(true);
        expect(wrapper.findComponent(SwSettingsUsageDataConsentCheckList).exists()).toBe(true);
    });
});
