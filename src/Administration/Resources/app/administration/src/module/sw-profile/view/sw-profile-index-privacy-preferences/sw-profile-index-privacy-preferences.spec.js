import { mount } from '@vue/test-utils';
// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- it uses the component itself, not its declaration
import SwSettingsUsageDataProfileConsent from 'src/module/sw-settings-usage-data/component/sw-settings-usage-data-profile-consent/src/sw-settings-usage-data-profile-consent';
import SwProfileIndexPrivacyPreferences from './index';

describe('/module/sw-profile/view/sw-profile-index-privacy-preferences', () => {
    it('shows user data consent component', async () => {
        const wrapper = await mount(SwProfileIndexPrivacyPreferences, {
            global: {
                stubs: {
                    SwSettingsUsageDataProfileConsent,
                },
            },
        });

        expect(wrapper.findComponent(SwSettingsUsageDataProfileConsent).exists()).toBe(true);
    });
});
