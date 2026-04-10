import { mount } from '@vue/test-utils';
import { MtSwitch, MtLoader } from '@shopware-ag/meteor-component-library';
import SwSettingsUsageDataUserDataConsentCard from './index';

describe('module/sw-settings-usage-data/component/sw-settings-usage-data-consent-modal/subcomponents/sw-settings-usage-data-user-data-consent-card', () => {
    it('emits a model update', async () => {
        const wrapper = await mount(SwSettingsUsageDataUserDataConsentCard, {
            props: {
                consent: false,
                isLoading: false,
            },
        });

        const consentSwitch = wrapper.getComponent(MtSwitch);

        await consentSwitch.vm.$emit('update:modelValue', true);
        await consentSwitch.vm.$emit('update:modelValue', false);

        expect(wrapper.emitted('update:consent')).toEqual([
            [true],
            [false],
        ]);
    });

    it('is disabled while loading', async () => {
        const wrapper = await mount(SwSettingsUsageDataUserDataConsentCard, {
            props: {
                consent: false,
                isLoading: true,
            },
            attachTo: document.body,
        });

        const consentSwitch = wrapper.getComponent(MtSwitch);

        expect(consentSwitch.props('disabled')).toBe(true);
        expect(wrapper.findComponent(MtLoader).exists()).toBe(true);
    });

    it('hides the switch when configured', async () => {
        const wrapper = await mount(SwSettingsUsageDataUserDataConsentCard, {
            props: {
                consent: false,
                hideSwitch: true,
            },
        });

        expect(wrapper.findComponent(MtSwitch).exists()).toBe(false);
    });
});
